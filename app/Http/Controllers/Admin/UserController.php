<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Admin;

use App\Audit\AuditLog;
use App\Authorization\InvalidRoleChange;
use App\Authorization\Permissions;
use App\Authorization\RoleManager;
use App\Authorization\UserType;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > Users (S-0931): invite people with roles, change their roles. Nobody
 * hands out or takes away a role that holds more than they hold themselves.
 */
class UserController extends Controller
{
    public function __construct(private readonly Permissions $permissions) {}

    public function index(Request $request): Response
    {
        $actor = $this->currentUser($request);
        $roles = $this->roles();
        $held = DB::table('user_roles')->where('tenant_id', $actor->tenant_id)->get(['user_id', 'role_id'])->groupBy('user_id');

        return Inertia::render('admin/Users', [
            'users' => User::query()->orderBy('name')->orderBy('id')->paginate(100)->through(fn (User $u): array => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'type' => $u->user_type->value, 'two_factor' => $u->two_factor_confirmed_at !== null, 'portal' => $u->user_type === UserType::Portal,
                'roles' => $held->get($u->id)?->pluck('role_id')->all() ?? [],
            ]),
            'roles' => $roles->map(fn (Role $r): array => ['value' => $r->id, 'text' => $r->name_string !== null ? __($r->name_string) : $r->key, 'allowed' => $this->mayHandOut($actor, $r)])->all(),
        ]);
    }

    public function store(Request $request, RoleManager $manager, AuditLog $audit, TenantContext $tenant): RedirectResponse
    {
        $actor = $this->currentUser($request);
        /** @var array{name: string, email: string, roles: list<string>} $data */
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'max:254', 'email:rfc', Rule::unique('users', 'email')->where('tenant_id', $tenant->id())],
            'roles' => ['required', 'list', 'min:1', 'max:10'],
            'roles.*' => ['ulid', 'distinct'],
        ])->validate();
        $roles = $this->chosen($actor, $data['roles']);

        $user = DB::transaction(function () use ($data, $roles, $manager, $audit, $actor): User {
            // No usable password: the person sets one through the mailed link,
            // which only reaches this address, so the address counts as verified.
            $user = User::query()->forceCreate(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make(Str::random(64)), 'email_verified_at' => now()]);
            foreach ($roles as $role) {
                $manager->assign($user, $role, $actor);
            }
            $audit->record('users.invited', $user, ['roles' => $roles->pluck('key')->all()], $actor);

            return $user;
        });
        $sent = Password::broker()->sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
        Inertia::flash('toast', ['type' => $sent ? 'success' : 'warning', 'message' => __($sent ? 'core:users.invited' : 'core:users.invited_no_mail')]);

        return to_route('admin.users.index');
    }

    public function update(Request $request, string $user, RoleManager $manager): RedirectResponse
    {
        $actor = $this->currentUser($request);
        $person = User::query()->findOrFail($user);
        abort_if($person->id === $actor->id || $person->user_type === UserType::Portal, 403);
        /** @var array{roles: list<string>} $data */
        $data = Validator::make($request->all(), ['roles' => ['present', 'list', 'max:10'], 'roles.*' => ['ulid', 'distinct']])->validate();
        $held = array_values(array_filter(DB::table('user_roles')->where('tenant_id', $actor->tenant_id)->where('user_id', $person->id)->pluck('role_id')->all(), is_string(...)));
        $add = $this->chosen($actor, array_values(array_diff($data['roles'], $held)));
        $remove = $this->chosen($actor, array_values(array_diff($held, $data['roles'])));

        try {
            DB::transaction(function () use ($add, $remove, $person, $manager, $actor): void {
                foreach ($add as $role) {
                    $manager->assign($person, $role, $actor);
                }
                foreach ($remove as $role) {
                    $manager->revoke($person, $role, $actor);
                }
            });
        } catch (InvalidRoleChange) {
            throw ValidationException::withMessages(['roles' => __('core:users.last_admin')]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:users.saved')]);

        return to_route('admin.users.index');
    }

    /** @return Collection<int, Role> */
    private function roles(): Collection
    {
        return Role::query()->where('is_portal', false)->orderBy('id')->get();
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Role>
     */
    private function chosen(User $actor, array $ids): Collection
    {
        $roles = $this->roles()->whereIn('id', $ids)->values();
        abort_if($roles->count() !== count($ids), 404);
        abort_if($roles->contains(fn (Role $r): bool => ! $this->mayHandOut($actor, $r)), 403);

        return $roles;
    }

    private function mayHandOut(User $actor, Role $role): bool
    {
        return array_diff($role->permissionKeys(), $this->permissions->of($actor)) === [];
    }
}

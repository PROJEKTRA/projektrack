<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\Events\PermissionsChanged;
use App\Authorization\Events\UserRolesChanged;
use App\Authorization\InvalidRoleChange;
use App\Authorization\Permissions;
use App\Authorization\ProjectMemberships;
use App\Authorization\ProjectScoped;
use App\Authorization\RoleManager;
use App\Authorization\RoleTemplates;
use App\Authorization\UnknownPermission;
use App\Authorization\UserType;
use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Support\FakeModules;
use Tests\Support\TenantIsolation;

/*
 * S-0906: global roles, project roles, deny by default and user types.
 * Synthetic modules stand in for projects and timesheets.
 */

function installWorkModules(array $timesheetsExtra = []): void
{
    FakeModules::install([
        'projects' => [
            'permissions' => ['projects.view', 'projects.manage'],
            'role_defaults' => ['project_lead' => ['projects.view', 'projects.manage'], 'employee' => ['projects.view']],
        ],
        'timesheets' => [
            'requires' => ['core', 'projects'],
            'permissions' => ['timesheets.view', 'timesheets.book', 'timesheets.approve', 'timesheets.view_rates', ...$timesheetsExtra],
            'role_defaults' => [
                'employee' => ['timesheets.view', 'timesheets.book'],
                'controller' => ['timesheets.view', 'timesheets.approve', 'timesheets.view_rates'],
            ],
            'project_role_defaults' => ['project_lead' => ['timesheets.approve']],
        ],
    ]);
}

function role(string $key): Role
{
    return Role::query()->where('key', $key)->firstOrFail();
}

function projectRole(string $key): ProjectRole
{
    return ProjectRole::query()->where('key', $key)->firstOrFail();
}

/**
 * @param  array<string, array<string, list<string>>>  $map  user id => project id => project role ids
 */
function fakeMemberships(array $map): void
{
    app()->instance(ProjectMemberships::class, new class($map) implements ProjectMemberships
    {
        public function __construct(private array $map) {}

        public function projectRoleIds(User $user, string $projectId): array
        {
            return $this->map[$user->id][$projectId] ?? [];
        }

        public function allProjectRoleIds(User $user): array
        {
            return array_values(array_unique(array_merge([], ...array_values($this->map[$user->id] ?? []))));
        }
    });
    app()->forgetScopedInstances();
}

function inProject(string $projectId): ProjectScoped
{
    return new class($projectId) implements ProjectScoped
    {
        public function __construct(private string $id) {}

        public function scopedProjectId(): ?string
        {
            return $this->id;
        }
    };
}

function employee(string $name = 'Ana Test'): User
{
    $user = User::factory()->create(['name' => $name]);
    app(RoleManager::class)->assign($user, role('employee'));

    return $user;
}

beforeEach(function () {
    installWorkModules();
    app(TenantModules::class)->enable('timesheets');
    app()->forgetScopedInstances();
});

TenantIsolation::for(Role::class, fn () => Role::factory()->create());
TenantIsolation::for(ProjectRole::class, fn () => ProjectRole::factory()->create());

describe('role templates', function () {
    it('gives every new tenant the six global roles and three project roles', function () {
        expect(Role::query()->orderBy('key')->pluck('key')->all())
            ->toBe(['admin', 'controller', 'customer', 'employee', 'external', 'project_lead'])
            ->and(ProjectRole::query()->orderBy('key')->pluck('key')->all())
            ->toBe(['project_lead', 'project_member', 'project_viewer']);
    });

    it('requires two-factor for admin and controller and marks customer as a portal role', function () {
        expect(role('admin')->requires_two_factor)->toBeTrue()
            ->and(role('controller')->requires_two_factor)->toBeTrue()
            ->and(role('employee')->requires_two_factor)->toBeFalse()
            ->and(role('customer')->is_portal)->toBeTrue()
            ->and(role('customer')->licensed_user_type)->toBe(UserType::Portal)
            ->and(role('admin')->licensed_user_type)->toBe(UserType::Full);
    });

    it('installs the templates once per tenant, also when run again', function () {
        app(RoleTemplates::class)->install($this->tenant);

        expect(Role::query()->count())->toBe(6);
    });

    it('applies module suggestions once, when the tenant first enables the module', function () {
        $roles = app(RoleManager::class);

        expect(role('employee')->permissionKeys())->toContain('timesheets.book');

        $roles->revokePermissions(role('employee'), ['timesheets.book']);
        app(TenantModules::class)->disable('timesheets');
        app(TenantModules::class)->enable('timesheets');

        expect(role('employee')->permissionKeys())->not->toContain('timesheets.book');
    });
});

describe('deny by default', function () {
    it('gives a permission a module adds in an update to no role, not even admin', function () {
        $admin = User::factory()->create();
        app(RoleManager::class)->assign($admin, role('admin'));

        installWorkModules(['timesheets.lock_period']);

        expect(DB::table('role_permissions')->where('permission_key', 'timesheets.lock_period')->exists())->toBeFalse()
            ->and($admin->can('timesheets.lock_period'))->toBeFalse();

        app(RoleManager::class)->grant(role('admin'), ['timesheets.lock_period']);

        expect($admin->can('timesheets.lock_period'))->toBeTrue();
    });

    it('denies permissions nobody registered and refuses to grant them', function () {
        $admin = User::factory()->create();
        app(RoleManager::class)->assign($admin, role('admin'));

        expect($admin->can('invoices.finalize'))->toBeFalse()
            ->and(fn () => app(RoleManager::class)->grant(role('admin'), ['invoices.finalize']))
            ->toThrow(UnknownPermission::class);
    });

    it('ignores grants of a module the tenant has switched off', function () {
        $ana = employee();
        expect($ana->can('timesheets.book'))->toBeTrue();

        app(TenantModules::class)->disable('timesheets');

        expect($ana->can('timesheets.book'))->toBeFalse()
            ->and($ana->can('projects.view'))->toBeTrue();
    });

    it('gives a user without roles nothing', function () {
        $user = User::factory()->create();

        expect(app(Permissions::class)->of($user))->toBe([])
            ->and($user->can('timesheets.view'))->toBeFalse();
    });

    it('answers a route without the permission with 403', function () {
        Route::middleware(['web', 'auth', 'permission:timesheets.approve'])->get('/_test/approve', fn () => 'approved');

        $this->actingAs(employee())->get('/_test/approve')->assertForbidden();

        $controller = User::factory()->withTwoFactor()->create();
        app(RoleManager::class)->assign($controller, role('controller'));

        $this->actingAs($controller)->get('/_test/approve')->assertOk();
    });
});

describe('global and project roles', function () {
    it('lets an employee who leads one project approve times in that project only', function () {
        $ana = employee('Ana Test');
        fakeMemberships([$ana->id => ['gg-operations' => [projectRole('project_lead')->id]]]);

        expect($ana->can('timesheets.approve'))->toBeFalse()
            ->and($ana->can('timesheets.approve', inProject('gg-operations')))->toBeTrue()
            ->and($ana->can('timesheets.approve', inProject('another-project')))->toBeFalse()
            ->and(Gate::forUser($ana)->allows('timesheets.book', inProject('another-project')))->toBeTrue();
    });

    it('checks the project of a route parameter', function () {
        $ana = employee();
        fakeMemberships([$ana->id => ['gg-operations' => [projectRole('project_lead')->id]]]);
        Route::middleware(['web', 'auth', 'permission:timesheets.approve,project'])
            ->get('/_test/projects/{project}/approve', fn () => 'approved');

        $this->actingAs($ana)->get('/_test/projects/gg-operations/approve')->assertOk();
        $this->actingAs($ana)->get('/_test/projects/another-project/approve')->assertForbidden();
    });

    it('does not let a project role grant permissions of a disabled module', function () {
        $ana = employee();
        fakeMemberships([$ana->id => ['gg-operations' => [projectRole('project_lead')->id]]]);

        app(TenantModules::class)->disable('timesheets');

        expect($ana->can('timesheets.approve', inProject('gg-operations')))->toBeFalse();
    });
});

describe('user types', function () {
    it('makes a user Light while all their permissions are in the Light set', function () {
        $ana = employee();

        expect($ana->fresh()?->user_type)->toBe(UserType::Full)
            ->and(app(RoleManager::class)->fullReason($ana))->toBe('projects.view');

        app(RoleManager::class)->revokePermissions(role('employee'), ['projects.view']);

        expect($ana->fresh()?->user_type)->toBe(UserType::Light)
            ->and(app(RoleManager::class)->fullReason($ana))->toBeNull();
    });

    it('makes a user Full as soon as a role grants more, and names the permission', function () {
        $ana = employee();
        app(RoleManager::class)->revokePermissions(role('employee'), ['projects.view']);
        expect($ana->fresh()?->user_type)->toBe(UserType::Light);

        app(RoleManager::class)->grant(role('employee'), ['projects.manage']);

        expect($ana->fresh()?->user_type)->toBe(UserType::Full)
            ->and(app(RoleManager::class)->fullReason($ana))->toBe('projects.manage');
    });

    it('counts project roles when deriving the type', function () {
        $ana = employee();
        app(RoleManager::class)->revokePermissions(role('employee'), ['projects.view']);
        fakeMemberships([$ana->id => ['gg-operations' => [projectRole('project_lead')->id]]]);

        app(RoleManager::class)->grantInProject(projectRole('project_lead'), ['projects.manage']);

        expect($ana->fresh()?->user_type)->toBe(UserType::Full);
    });

    it('lets a Light user reach only time, expenses, own tasks and approvals', function () {
        $ana = employee();
        $ana->user_type = UserType::Light;
        $ana->saveQuietly();
        app()->forgetScopedInstances();

        expect($ana->can('timesheets.book'))->toBeTrue()
            ->and($ana->can('projects.view'))->toBeFalse();
    });

    it('keeps portal accounts and internal roles apart', function () {
        $portal = User::factory()->portal()->create();
        $roles = app(RoleManager::class);

        expect(fn () => $roles->assign($portal, role('employee')))->toThrow(InvalidRoleChange::class)
            ->and(fn () => $roles->assign(User::factory()->create(), role('customer')))->toThrow(InvalidRoleChange::class)
            ->and(fn () => $roles->grant(role('customer'), ['timesheets.view']))->toThrow(InvalidRoleChange::class);

        $roles->assign($portal, role('customer'));

        expect($portal->fresh()?->user_type)->toBe(UserType::Portal)
            ->and(app(Permissions::class)->of($portal))->toBe([]);
    });
});

describe('safety rules', function () {
    it('never removes the administrator role from the last administrator', function () {
        $admin = User::factory()->create();
        $roles = app(RoleManager::class);
        $roles->assign($admin, role('admin'));

        expect(fn () => $roles->revoke($admin, role('admin')))->toThrow(InvalidRoleChange::class);

        $second = User::factory()->create();
        $roles->assign($second, role('admin'));
        $roles->revoke($admin, role('admin'));

        expect($admin->can('core.roles.manage'))->toBeFalse()
            ->and($second->can('core.roles.manage'))->toBeTrue();
    });

    it('keeps core.roles.manage on the administrator role', function () {
        expect(fn () => app(RoleManager::class)->revokePermissions(role('admin'), ['core.roles.manage']))
            ->toThrow(InvalidRoleChange::class);
    });

    it('refuses to combine a user and a role of different tenants', function () {
        $ana = employee();
        $foreignRole = TenantIsolation::inOtherTenant(fn () => role('admin'));

        expect(fn () => app(RoleManager::class)->assign($ana, $foreignRole))->toThrow(InvalidRoleChange::class);
    });

    it('denies everything to a user of another tenant', function () {
        $foreigner = app(TenantContext::class)->run(Tenant::factory()->create(), function () {
            $user = User::factory()->create();
            app(RoleManager::class)->assign($user, role('admin'));

            return $user;
        });

        expect(app(Permissions::class)->allows($foreigner, 'core.admin'))->toBeFalse();
    });

    it('announces role and grant changes for the audit log', function () {
        Event::fake([UserRolesChanged::class, PermissionsChanged::class]);
        $actor = User::factory()->create();
        $ana = User::factory()->create();

        app(RoleManager::class)->assign($ana, role('employee'), $actor);
        app(RoleManager::class)->grant(role('employee'), ['timesheets.approve'], $actor);

        Event::assertDispatched(UserRolesChanged::class, fn (UserRolesChanged $e) => $e->userId === $ana->id && $e->assigned && $e->actorId === $actor->id);
        Event::assertDispatched(PermissionsChanged::class, fn (PermissionsChanged $e) => $e->granted === ['timesheets.approve']);
    });
});

it('shares the effective permissions with the frontend', function () {
    $this->actingAs(employee())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('timesheets.book')
            && ! collect($permissions)->contains('timesheets.approve')));
});

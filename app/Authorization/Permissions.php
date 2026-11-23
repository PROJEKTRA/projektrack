<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use App\Models\User;
use App\Modules\TenantModules;
use App\Support\Lists;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Effective permissions of a user in the current tenant (S-0906).
 *
 * Deny by default: a permission counts only if
 * - a role of the user grants it (globally, or a project role in that project),
 * - it is registered by the core or an installed module,
 * - its module is enabled for the tenant, and
 * - the user's type may hold it (Light and Portal ceilings).
 *
 * Scoped per request or job; changes call flush().
 */
final class Permissions
{
    /** @var array<string, list<string>> */
    private array $roleIds = [];

    /** @var array<string, list<string>> */
    private array $global = [];

    /** @var array<string, list<string>> */
    private array $project = [];

    public function __construct(
        private readonly PermissionCatalog $catalog,
        private readonly TenantModules $modules,
        private readonly ProjectMemberships $memberships,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return list<string> ids of the user's global roles
     */
    public function roleIdsOf(User $user): array
    {
        return $this->roleIds[$this->cacheKey($user)] ??= Lists::strings(DB::table('user_roles')
            ->where('tenant_id', $this->tenant->id())
            ->where('user_id', $user->id)
            ->orderBy('role_id')
            ->pluck('role_id'));
    }

    /**
     * Permissions the user holds everywhere in the tenant.
     *
     * @return list<string>
     */
    public function of(User $user): array
    {
        return $this->global[$this->cacheKey($user)] ??= $this->effective($user, $this->grantedTo($this->roleIdsOf($user), 'role_permissions', 'role_id'));
    }

    /**
     * Permissions the user holds inside one project: global ones plus those of
     * the user's project roles there.
     *
     * @return list<string>
     */
    public function inProject(User $user, string $projectId): array
    {
        return $this->project[$this->cacheKey($user)."|{$projectId}"] ??= array_values(array_unique([
            ...$this->of($user),
            ...$this->effective($user, $this->grantedTo(
                $this->memberships->projectRoleIds($user, $projectId),
                'project_role_permissions',
                'project_role_id',
            )),
        ]));
    }

    /**
     * Permissions the user holds everywhere or in at least one project.
     *
     * @return list<string>
     */
    public function anywhere(User $user): array
    {
        return array_values(array_unique([
            ...$this->of($user),
            ...$this->effective($user, $this->grantedTo($this->memberships->allProjectRoleIds($user), 'project_role_permissions', 'project_role_id')),
        ]));
    }

    public function allows(User $user, string $permission, ?string $projectId = null): bool
    {
        if ($user->tenant_id !== $this->tenant->id()) {
            return false;
        }

        $held = $projectId === null ? $this->of($user) : $this->inProject($user, $projectId);

        return in_array($permission, $held, true);
    }

    /**
     * Everything granted to the user, before ceilings and module state. Used to
     * derive the user type (ADR-0003), which must see what roles would give.
     *
     * @return list<string>
     */
    public function rawGrants(User $user): array
    {
        $keys = [
            ...$this->grantedTo($this->roleIdsOf($user), 'role_permissions', 'role_id'),
            ...$this->grantedTo($this->memberships->allProjectRoleIds($user), 'project_role_permissions', 'project_role_id'),
        ];

        return array_values(array_unique(array_filter($keys, fn (string $key): bool => $this->catalog->has($key))));
    }

    public function flush(): void
    {
        $this->roleIds = [];
        $this->global = [];
        $this->project = [];
    }

    private function cacheKey(User $user): string
    {
        return $this->tenant->id().'|'.$user->id;
    }

    /**
     * @param  list<string>  $roleIds
     * @return list<string>
     */
    private function grantedTo(array $roleIds, string $table, string $column): array
    {
        if ($roleIds === []) {
            return [];
        }

        return Lists::strings(DB::table($table)
            ->where('tenant_id', $this->tenant->id())
            ->whereIn($column, $roleIds)
            ->distinct()
            ->orderBy('permission_key')
            ->pluck('permission_key'));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function effective(User $user, array $keys): array
    {
        $type = $user->user_type;

        return array_values(array_filter($keys, function (string $key) use ($type): bool {
            $module = $this->catalog->moduleOf($key);

            return $module !== null && $this->modules->isEnabled($module) && $type->allows($key);
        }));
    }
}

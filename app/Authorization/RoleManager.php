<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use App\Authorization\Events\PermissionsChanged;
use App\Authorization\Events\UserRolesChanged;
use App\Models\FieldPermission;
use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Lists;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The only way roles, grants and field overrides change (S-0906). Every change
 * keeps the user type (ADR-0003) and the request caches in step and raises an
 * event for the audit log.
 *
 * Permission checks for the acting admin (core.roles.manage, re-authentication,
 * no granting of permissions the actor lacks) belong to the admin endpoints
 * that call this service.
 */
final class RoleManager
{
    public function __construct(
        private readonly PermissionCatalog $catalog,
        private readonly Permissions $permissions,
        private readonly FieldGate $fields,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @throws InvalidRoleChange
     */
    public function assign(User $user, Role $role, ?User $actor = null): void
    {
        $this->sameTenant($user->tenant_id, $role->tenant_id);

        $portalUser = $user->user_type === UserType::Portal;
        if ($role->is_portal !== $portalUser) {
            throw new InvalidRoleChange($portalUser
                ? 'Portal users can only hold portal roles.'
                : 'Portal roles can only be given to portal users.');
        }

        $inserted = DB::table('user_roles')->insertOrIgnore([
            'tenant_id' => $this->tenant->id(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
        ]);

        if ($inserted > 0) {
            $this->changed();
            $this->refreshUserType($user);
            event(new UserRolesChanged($this->tenant->id(), $user->id, $role->id, true, $actor?->id));
        }
    }

    /**
     * @throws InvalidRoleChange
     */
    public function revoke(User $user, Role $role, ?User $actor = null): void
    {
        $this->sameTenant($user->tenant_id, $role->tenant_id);

        if ($role->key === Role::ADMIN && $this->isLastAdministrator($user, $role)) {
            throw new InvalidRoleChange('The last administrator of a tenant cannot lose the administrator role.');
        }

        $deleted = DB::table('user_roles')
            ->where('tenant_id', $this->tenant->id())
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->delete();

        if ($deleted > 0) {
            $this->changed();
            $this->refreshUserType($user);
            event(new UserRolesChanged($this->tenant->id(), $user->id, $role->id, false, $actor?->id));
        }
    }

    /**
     * Grant permissions to a global role. Deny by default means this is the only
     * way a role ever gets a permission, including the administrator role.
     *
     * @param  list<string>  $permissions
     *
     * @throws UnknownPermission
     * @throws InvalidRoleChange
     */
    public function grant(Role $role, array $permissions, ?User $actor = null): void
    {
        $this->sameTenant($role->tenant_id, $this->tenant->id());
        $this->catalog->persist($permissions);

        if ($role->is_portal) {
            foreach ($permissions as $permission) {
                if (! UserType::Portal->allows($permission)) {
                    throw new InvalidRoleChange("Portal roles cannot hold [{$permission}].");
                }
            }
        }

        $granted = $this->insertGrants('role_permissions', 'role_id', $role->id, $permissions);

        if ($granted !== []) {
            $this->afterRoleChange($role);
            event(new PermissionsChanged($this->tenant->id(), 'role', $role->id, $granted, [], $actor?->id));
        }
    }

    /**
     * @param  list<string>  $permissions
     *
     * @throws InvalidRoleChange
     */
    public function revokePermissions(Role $role, array $permissions, ?User $actor = null): void
    {
        $this->sameTenant($role->tenant_id, $this->tenant->id());

        if ($role->key === Role::ADMIN && in_array('core.roles.manage', $permissions, true)) {
            throw new InvalidRoleChange('The administrator role always keeps [core.roles.manage], so a tenant cannot lock itself out.');
        }

        $revoked = $this->deleteGrants('role_permissions', 'role_id', $role->id, $permissions);

        if ($revoked !== []) {
            $this->afterRoleChange($role);
            event(new PermissionsChanged($this->tenant->id(), 'role', $role->id, [], $revoked, $actor?->id));
        }
    }

    /**
     * @param  list<string>  $permissions
     *
     * @throws UnknownPermission
     */
    public function grantInProject(ProjectRole $role, array $permissions, ?User $actor = null): void
    {
        $this->sameTenant($role->tenant_id, $this->tenant->id());
        $this->catalog->persist($permissions);

        $granted = $this->insertGrants('project_role_permissions', 'project_role_id', $role->id, $permissions);

        if ($granted !== []) {
            $this->changed();
            $this->refreshAllUserTypes();
            event(new PermissionsChanged($this->tenant->id(), 'project_role', $role->id, $granted, [], $actor?->id));
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public function revokeInProject(ProjectRole $role, array $permissions, ?User $actor = null): void
    {
        $this->sameTenant($role->tenant_id, $this->tenant->id());

        $revoked = $this->deleteGrants('project_role_permissions', 'project_role_id', $role->id, $permissions);

        if ($revoked !== []) {
            $this->changed();
            $this->refreshAllUserTypes();
            event(new PermissionsChanged($this->tenant->id(), 'project_role', $role->id, [], $revoked, $actor?->id));
        }
    }

    /**
     * Require two-factor for everyone holding $role (S-0904). It can be added to
     * any role; it cannot be removed from the administrator and controller
     * templates. Roles with sensitive permissions require it regardless.
     *
     * @throws InvalidRoleChange
     */
    public function setTwoFactorRequired(Role $role, bool $required, ?User $actor = null): void
    {
        $this->sameTenant($role->tenant_id, $this->tenant->id());

        if (! $required && in_array($role->template_key, ['admin', 'controller'], true)) {
            throw new InvalidRoleChange('Two-factor authentication cannot be made optional for administrators and controllers.');
        }

        if ($role->requires_two_factor !== $required) {
            $role->forceFill(['requires_two_factor' => $required])->save();
            event(new PermissionsChanged($this->tenant->id(), 'role', $role->id, $required ? ['two_factor.required'] : [], $required ? [] : ['two_factor.required'], $actor?->id));
        }
    }

    /**
     * Override a declared field for one role. Null removes the override, so the
     * module default applies again.
     *
     * @throws InvalidRoleChange
     */
    public function setFieldAccess(Role $role, string $entity, string $field, ?FieldAccess $access, ?User $actor = null): void
    {
        $this->sameTenant($role->tenant_id, $this->tenant->id());

        if ($this->catalog->field($entity, $field) === null) {
            throw new InvalidRoleChange("The field [{$entity}.{$field}] is not declared by any installed module.");
        }

        $query = FieldPermission::query()->where('role_id', $role->id)->where('entity_type', $entity)->where('field', $field);

        if ($access === null) {
            $query->delete();
        } else {
            FieldPermission::query()->updateOrCreate(
                ['role_id' => $role->id, 'entity_type' => $entity, 'field' => $field],
                ['access' => $access],
            );
        }

        $this->changed();
        event(new PermissionsChanged($this->tenant->id(), 'field', $role->id, $access === null ? [] : ["{$entity}.{$field}:{$access->value}"], $access === null ? ["{$entity}.{$field}"] : [], $actor?->id));
    }

    /**
     * Apply a module's suggested grants to the tenant's template roles. Called
     * once, when the tenant first enables the module; later module updates that
     * add permissions grant nothing on their own.
     */
    public function applyModuleDefaults(string $module): void
    {
        foreach ($this->catalog->roleDefaults($module) as $template => $permissions) {
            $role = Role::query()->where('template_key', $template)->first();
            if ($role !== null && ! $role->is_portal) {
                $this->grant($role, $permissions);
            }
        }

        foreach ($this->catalog->projectRoleDefaults($module) as $template => $permissions) {
            $role = ProjectRole::query()->where('key', $template)->where('is_system', true)->first();
            if ($role !== null) {
                $this->grantInProject($role, $permissions);
            }
        }
    }

    /**
     * Derive Full or Light from what the user's roles grant (ADR-0003). Portal
     * and service accounts keep their kind.
     */
    public function refreshUserType(User $user): void
    {
        if (! $user->user_type->isDerived()) {
            return;
        }

        $type = $this->fullReason($user) === null ? UserType::Light : UserType::Full;

        if ($user->user_type !== $type) {
            $user->user_type = $type;
            $user->saveQuietly();
            $this->changed();
        }
    }

    /**
     * The permission that makes the user a Full user, shown to admins
     * ("Full user because of permission invoices.finalize"). Null for Light.
     */
    public function fullReason(User $user): ?string
    {
        foreach ($this->permissions->rawGrants($user) as $permission) {
            if (! UserType::isLight($permission)) {
                return $permission;
            }
        }

        return null;
    }

    private function afterRoleChange(Role $role): void
    {
        $this->changed();

        $role->forceFill(['licensed_user_type' => $this->roleType($role)])->saveQuietly();

        $role->users()->each(fn (User $user) => $this->refreshUserType($user));
    }

    private function roleType(Role $role): UserType
    {
        if ($role->is_portal) {
            return UserType::Portal;
        }

        foreach ($role->permissionKeys() as $permission) {
            if (! UserType::isLight($permission)) {
                return UserType::Full;
            }
        }

        return UserType::Light;
    }

    private function refreshAllUserTypes(): void
    {
        User::query()->whereIn('user_type', [UserType::Full->value, UserType::Light->value])
            ->each(fn (User $user) => $this->refreshUserType($user));
    }

    private function isLastAdministrator(User $user, Role $role): bool
    {
        $others = DB::table('user_roles')
            ->where('tenant_id', $this->tenant->id())
            ->where('role_id', $role->id)
            ->where('user_id', '!=', $user->id)
            ->exists();

        return ! $others;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string> the permissions that were not granted before
     */
    private function insertGrants(string $table, string $column, string $roleId, array $permissions): array
    {
        $existing = $this->existingGrants($table, $column, $roleId);
        $new = array_values(array_diff(array_unique($permissions), $existing));

        DB::table($table)->insert(array_map(fn (string $key): array => [
            'tenant_id' => $this->tenant->id(),
            $column => $roleId,
            'permission_key' => $key,
            'created_at' => now(),
        ], $new));

        return $new;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string> the permissions that were actually granted before
     */
    private function deleteGrants(string $table, string $column, string $roleId, array $permissions): array
    {
        $revoked = array_values(array_intersect($this->existingGrants($table, $column, $roleId), $permissions));

        DB::table($table)
            ->where('tenant_id', $this->tenant->id())
            ->where($column, $roleId)
            ->whereIn('permission_key', $revoked)
            ->delete();

        return $revoked;
    }

    /**
     * @return list<string>
     */
    private function existingGrants(string $table, string $column, string $roleId): array
    {
        return Lists::strings(DB::table($table)
            ->where('tenant_id', $this->tenant->id())
            ->where($column, $roleId)
            ->pluck('permission_key'));
    }

    /**
     * @throws InvalidRoleChange
     */
    private function sameTenant(string $a, string $b): void
    {
        if ($a !== $b || $a !== $this->tenant->id()) {
            throw new InvalidRoleChange('Roles and users of different tenants cannot be combined.');
        }
    }

    private function changed(): void
    {
        $this->permissions->flush();
        $this->fields->flush();
    }
}

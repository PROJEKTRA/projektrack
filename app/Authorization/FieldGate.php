<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use App\Models\User;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Field-level permissions (security baseline, section 4). API resources,
 * Inertia props, exports, search, notifications and AI context builders all
 * filter through this gate. Hidden fields are omitted, never blanked.
 *
 * Rules for a declared field:
 * - each of the user's roles gives its override from field_permissions, or the
 *   module's default; the user gets the highest of these (a user without roles
 *   gets the default);
 * - the declared permission (for example "timesheets.view_rates"), held
 *   globally or in the project, gives at least read access;
 * - a disabled module hides its fields; portal users never see financial or
 *   security fields.
 *
 * Fields nobody declared are not sensitive and pass unchanged.
 */
final class FieldGate
{
    /** @var array<string, array<string, FieldAccess>> */
    private array $overrides = [];

    public function __construct(
        private readonly PermissionCatalog $catalog,
        private readonly Permissions $permissions,
        private readonly TenantModules $modules,
        private readonly TenantContext $tenant,
    ) {}

    public function access(User $user, string $entity, string $field, ?string $projectId = null): FieldAccess
    {
        $declaration = $this->catalog->field($entity, $field);

        if ($declaration === null) {
            return FieldAccess::Write;
        }

        if (! $this->modules->isEnabled($declaration->moduleKey) || $user->tenant_id !== $this->tenant->id()) {
            return FieldAccess::Hidden;
        }

        if ($user->user_type === UserType::Portal && $declaration->risk->mustDefaultToHidden()) {
            return FieldAccess::Hidden;
        }

        $roleIds = $this->permissions->roleIdsOf($user);
        $overrides = $this->overridesFor($roleIds, $entity);

        $access = $roleIds === [] ? $declaration->default : FieldAccess::Hidden;
        foreach ($roleIds as $roleId) {
            $access = $access->max($overrides["{$roleId}|{$field}"] ?? $declaration->default);
        }

        if ($declaration->permission !== null && $this->permissions->allows($user, $declaration->permission, $projectId)) {
            $access = $access->max(FieldAccess::Read);
        }

        return $access;
    }

    public function canRead(User $user, string $entity, string $field, ?string $projectId = null): bool
    {
        return $this->access($user, $entity, $field, $projectId)->canRead();
    }

    /**
     * @return list<string> declared fields of $entity the user may not read
     */
    public function hiddenFields(User $user, string $entity, ?string $projectId = null): array
    {
        $hidden = [];
        foreach ($this->catalog->fieldsOf($entity) as $declaration) {
            if (! $this->canRead($user, $entity, $declaration->field, $projectId)) {
                $hidden[] = $declaration->field;
            }
        }

        return $hidden;
    }

    /**
     * Remove every field the user may not read.
     *
     * @template TValue
     *
     * @param  array<string, TValue>  $attributes
     * @return array<string, TValue>
     */
    public function filter(User $user, string $entity, array $attributes, ?string $projectId = null): array
    {
        return array_diff_key($attributes, array_flip($this->hiddenFields($user, $entity, $projectId)));
    }

    public function flush(): void
    {
        $this->overrides = [];
    }

    /**
     * @param  list<string>  $roleIds
     * @return array<string, FieldAccess> "roleId|field" => access
     */
    private function overridesFor(array $roleIds, string $entity): array
    {
        if ($roleIds === []) {
            return [];
        }

        $cacheKey = implode(',', $roleIds)."|{$entity}";

        if (! isset($this->overrides[$cacheKey])) {
            $rows = DB::table('field_permissions')
                ->where('tenant_id', $this->tenant->id())
                ->whereIn('role_id', $roleIds)
                ->where('entity_type', $entity)
                ->get(['role_id', 'field', 'access']);

            $map = [];
            foreach ($rows as $row) {
                /** @var object{role_id: string, field: string, access: string} $row */
                $access = FieldAccess::tryFrom($row->access);
                if ($access !== null) {
                    $map["{$row->role_id}|{$row->field}"] = $access;
                }
            }

            $this->overrides[$cacheKey] = $map;
        }

        return $this->overrides[$cacheKey];
    }
}

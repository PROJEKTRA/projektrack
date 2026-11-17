<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use App\Models\Role;
use App\Models\User;

/**
 * Who must use two-factor authentication (S-0904, security baseline 3.2).
 *
 * Required when one of the user's roles is flagged "requires two-factor", or
 * when anything the user is granted reaches money, permissions or licensing.
 * The second rule cannot be switched off: a role editor may extend the
 * requirement, never remove it from administrators or finance.
 */
final class TwoFactorRequirement
{
    /** Module permission namespaces that reach money or licensing. */
    public const array SENSITIVE_PREFIXES = ['invoices.', 'tax.', 'rates.', 'payments.', 'licensing.'];

    /** Single permissions that reveal rates, costs or amounts. */
    public const array SENSITIVE_PERMISSIONS = ['timesheets.view_rates', 'service_reports.view_amounts'];

    public function __construct(private readonly Permissions $permissions) {}

    public function isRequired(User $user): bool
    {
        return $this->reason($user) !== null;
    }

    /**
     * Why two-factor is required, for the admin and the user: "role:<key>" or
     * "permission:<key>". Null when it is optional.
     */
    public function reason(User $user): ?string
    {
        if (! $user->user_type->isDerived()) {
            return null;
        }

        $roleIds = $this->permissions->roleIdsOf($user);

        if ($roleIds !== []) {
            $role = Role::query()->whereIn('id', $roleIds)->where('requires_two_factor', true)->orderBy('key')->value('key');

            if (is_string($role)) {
                return "role:{$role}";
            }
        }

        foreach ($this->permissions->rawGrants($user) as $permission) {
            if (self::isSensitive($permission)) {
                return "permission:{$permission}";
            }
        }

        return null;
    }

    public static function isSensitive(string $permission): bool
    {
        if ((PermissionCatalog::CORE[$permission] ?? null) === 'high' || in_array($permission, self::SENSITIVE_PERMISSIONS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_PREFIXES as $prefix) {
            if (str_starts_with($permission, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

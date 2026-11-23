<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use App\Authorization\Permissions;
use App\Doctor\DoctorAccess;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Who sees and changes the license (S-0976). It belongs to the installation
 * shared by every tenant, so only the operator tenant does, with its own
 * permission; other tenants never see seats or customer of the installation.
 */
final readonly class LicenseAccess
{
    public function __construct(private Permissions $permissions, private DoctorAccess $operator, private TenantContext $tenants) {}

    public function allows(User $user): bool
    {
        return $this->tenants->has()
            && $this->permissions->allows($user, 'core.license.manage')
            && $this->operator->operatorTenant()?->id === $this->tenants->id();
    }
}

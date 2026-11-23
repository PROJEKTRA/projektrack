<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Doctor;

use App\Authorization\Permissions;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Who sees the system check (S-0988). The report describes the installation
 * shared by every tenant, so only administrators of the operator tenant see
 * it: the one named in DOCTOR_OPERATOR_TENANT, or the only tenant of a
 * single-company installation. Customer tenants on a shared installation
 * never learn what is wrong with a server they cannot fix.
 */
final readonly class DoctorAccess
{
    public function __construct(
        private Permissions $permissions,
        private TenantContext $tenants,
    ) {}

    public function allows(User $user): bool
    {
        if (! $this->tenants->has() || ! $this->permissions->allows($user, 'core.admin')) {
            return false;
        }

        $operator = $this->operatorTenant();

        return $operator !== null && $operator->id === $this->tenants->id();
    }

    public function operatorTenant(): ?Tenant
    {
        $slug = config('doctor.operator_tenant');
        $slug = is_string($slug) ? $slug : '';

        if ($slug !== '') {
            return Tenant::query()->where('slug', $slug)->first();
        }

        return Tenant::query()->count() === 1 ? Tenant::query()->first() : null;
    }
}

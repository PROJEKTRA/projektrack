<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use App\Doctor\DoctorAccess;
use App\Modules\Edition;
use App\Tenancy\TenantContext;

/**
 * What the license allows (S-0977, ADR-0003 section 6). Community is never
 * limited. Commercial modules are read-only without a license that allows
 * them: nothing is hidden or deleted.
 */
final class EditionGate
{
    public function __construct(private readonly InstanceLicense $licenses, private readonly DoctorAccess $operator, private readonly TenantContext $tenants) {}

    /** Commercial functions may be used, not only viewed. */
    public function allows(Edition $edition): bool
    {
        if ($edition === Edition::Community) {
            return true;
        }
        if ($edition === Edition::Vendor) {
            return config('licensing.vendor_instance') === true && $this->tenants->has() && $this->operator->operatorTenant()?->id === $this->tenants->id();
        }
        $license = $this->licenses->current();

        return $license !== null && $this->licenses->status()?->allowsUse() === true && $this->rank($license->edition) >= $this->rank($edition);
    }

    private function rank(Edition $edition): int
    {
        return match ($edition) {
            Edition::Community => 0,
            Edition::Business => 1,
            Edition::Enterprise => 2,
            Edition::Partner => 3,
            Edition::Vendor => 4,
        };
    }
}

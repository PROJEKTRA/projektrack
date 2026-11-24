<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use App\Modules\Edition;

/**
 * What the license allows (S-0977, ADR-0003 section 6). Community is never
 * limited. Commercial modules are read-only without a license that allows
 * them: nothing is hidden or deleted.
 */
final class EditionGate
{
    public function __construct(private readonly InstanceLicense $licenses) {}

    /** Commercial functions may be used, not only viewed. */
    public function allows(Edition $edition): bool
    {
        if ($edition === Edition::Community) {
            return true;
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
            // Runs only on the licensor's own instance; no license is sold for it.
            Edition::Vendor => 4,
        };
    }
}

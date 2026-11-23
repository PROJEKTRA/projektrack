<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

/** Effective state of a license (ADR-0003 section 6). Nothing here ever locks Community functions. */
enum LicenseStatus: string
{
    case Active = 'active';
    case PendingPayment = 'pending_payment';
    case Grace = 'grace';
    case ReadOnly = 'read_only';
    case NotYetValid = 'not_yet_valid';

    /** Commercial features may be used, not only viewed and exported. */
    public function allowsUse(): bool
    {
        return in_array($this, [self::Active, self::PendingPayment, self::Grace], true);
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

/**
 * Risk class of a declared field (modules.md, field_permissions).
 */
enum FieldRisk: string
{
    case None = 'none';
    case Personal = 'personal';
    case Financial = 'financial';
    case Security = 'security';

    /** Rates, costs, margins, salaries, bank details and secrets are hidden unless granted. */
    public function mustDefaultToHidden(): bool
    {
        return $this === self::Financial || $this === self::Security;
    }
}

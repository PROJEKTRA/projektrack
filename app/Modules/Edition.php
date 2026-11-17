<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

/**
 * Editions as declared in module.json (ADR-0003). A higher edition includes the
 * lower ones; "vendor" modules only run on the PROJEKTRA instance.
 */
enum Edition: string
{
    case Community = 'community';
    case Business = 'business';
    case Enterprise = 'enterprise';
    case Partner = 'partner';
    case Vendor = 'vendor';
}

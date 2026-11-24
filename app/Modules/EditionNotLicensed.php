<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

use RuntimeException;

final class EditionNotLicensed extends RuntimeException
{
    public function __construct(public readonly string $module)
    {
        parent::__construct("The module {$module} needs a license of its edition.");
    }
}

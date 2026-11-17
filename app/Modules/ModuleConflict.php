<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

use DomainException;

final class ModuleConflict extends DomainException
{
    public function __construct(public readonly string $module, public readonly string $conflictsWith)
    {
        parent::__construct("Module \"{$module}\" cannot be enabled together with \"{$conflictsWith}\"");
    }
}

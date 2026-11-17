<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

use DomainException;

final class ModuleStillRequired extends DomainException
{
    /**
     * @param  list<string>  $dependents
     */
    public function __construct(public readonly string $module, public readonly array $dependents)
    {
        parent::__construct(sprintf('Module "%s" is still required by: %s', $module, implode(', ', $dependents)));
    }
}

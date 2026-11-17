<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

use RuntimeException;

/**
 * A module.json that cannot be accepted. The message is written for the
 * operator reading the log, the problems list is for tests and the admin UI.
 */
final class InvalidModuleManifest extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly string $path, public readonly array $problems)
    {
        parent::__construct(sprintf('Module manifest %s is invalid: %s', $path, implode('; ', $problems)));
    }
}

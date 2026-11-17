<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

/**
 * A module was switched on for a tenant. $firstTime is true when the tenant
 * never had it before; only then are the module's role defaults applied.
 */
final readonly class ModuleEnabled
{
    public function __construct(
        public string $tenantId,
        public string $module,
        public bool $firstTime,
        public ?string $actorId,
    ) {}
}

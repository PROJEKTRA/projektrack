<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

/**
 * A module was switched off for a tenant. Its data and grants stay; its
 * permissions simply stop counting while it is off.
 */
final readonly class ModuleDisabled
{
    public function __construct(
        public string $tenantId,
        public string $module,
        public ?string $actorId,
    ) {}
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Tenancy;

use LogicException;

/**
 * Thrown instead of returning rows of every tenant. Seeing this means the code
 * path forgot to set a tenant (for example a job dispatched without one).
 */
final class MissingTenantContext extends LogicException
{
    public function __construct()
    {
        parent::__construct('No tenant context is set; tenant-owned data cannot be accessed.');
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use InvalidArgumentException;

final class UnknownPermission extends InvalidArgumentException
{
    public function __construct(public readonly string $permission)
    {
        parent::__construct("The permission [{$permission}] is not registered by the core or any installed module.");
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

use InvalidArgumentException;

final class UnknownModule extends InvalidArgumentException
{
    public function __construct(public readonly string $key)
    {
        parent::__construct("Unknown module \"{$key}\"");
    }
}

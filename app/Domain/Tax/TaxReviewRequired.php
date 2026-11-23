<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Domain\Tax;

use RuntimeException;

/**
 * No rule decides this case. Never guessed: the document cannot be issued
 * until the data or the rules say more. The reason is a string key.
 */
final class TaxReviewRequired extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Domain\Tax;

final readonly class TaxDecision
{
    /** @param string $category UNTDID 5305 code (EN 16931) */
    public function __construct(
        public TaxCase $case,
        public int $ratePpm,
        public string $category,
        public string $ruleRef,
    ) {}
}

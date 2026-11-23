<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Domain\Tax;

/**
 * What the decision depends on. Plain values, no database.
 */
final readonly class TaxContext
{
    /** @param string $rateClass standard, reduced or exempt */
    public function __construct(
        public string $sellerCountry,
        public string $sellerStatus,
        public string $customerCountry,
        public string $customerStatus,
        public bool $customerHasVatId,
        public string $rateClass,
        public string $date,
    ) {}
}

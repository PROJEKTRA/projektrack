<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Contracts;

/**
 * Frozen values of approved time, for service reports and invoices.
 */
interface TimeValues
{
    /**
     * @param  list<string>  $entryIds
     * @return array<string, array{rate: string, currency: string, minutes: int, amount_minor: int, source: string}> entry id => value; entries without a rate are missing
     */
    public function of(array $entryIds): array;
}

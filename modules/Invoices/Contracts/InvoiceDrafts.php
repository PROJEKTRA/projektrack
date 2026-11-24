<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Contracts;

use App\Models\User;

/**
 * Lets other modules draft an invoice; a person checks and issues it.
 */
interface InvoiceDrafts
{
    /**
     * @param  list<array{description: string, quantity: string, unit: string, unit_price: string, rate_class: string}>  $lines
     * @return string id of the draft
     */
    public function draft(string $companyId, string $from, string $to, array $lines, User $actor): string;

    /** @return array<string, string> what keeps a draft from being issued, key => text */
    public function problems(string $invoiceId): array;

    /** Throw a draft away; refused once it is issued. */
    public function discard(string $invoiceId, User $actor): void;

    /** @return array{status: string, currency: string, net_minor: int, settled: bool, void: bool, due_date: string|null}|null settled: issued and paid in full, nothing given back; void: cancelled, or credited and refunded in full */
    public function state(string $invoiceId): ?array;
}

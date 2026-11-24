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

    /** @return array{status: string, net_minor: int, credited_minor: int, settled: bool, due_date: string|null}|null settled: issued and paid in full, nothing given back */
    public function state(string $invoiceId): ?array;
}

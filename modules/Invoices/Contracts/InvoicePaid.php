<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Contracts;

/** An invoice is paid in full. */
final readonly class InvoicePaid
{
    public function __construct(public string $invoiceId) {}
}

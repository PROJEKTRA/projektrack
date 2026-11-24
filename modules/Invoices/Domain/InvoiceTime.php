<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Time billed by an invoice; billed once at a time.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property string $time_entry_id
 * @property string|null $active_key
 */
#[Fillable(['invoice_id', 'time_entry_id', 'active_key'])]
class InvoiceTime extends Model
{
    use BelongsToTenant, HasUlids;
}

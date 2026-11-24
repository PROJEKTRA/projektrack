<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One mail of an invoice to one recipient.
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $recipient
 * @property string $status
 * @property string $sent_by
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['invoice_id', 'recipient', 'status', 'sent_by'])]
class InvoiceDelivery extends Model
{
    use BelongsToTenant, HasUlids;
}

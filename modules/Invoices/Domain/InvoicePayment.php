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
use LogicException;

/**
 * Money received for an invoice. A wrong one is balanced by a negative one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property CarbonInterface $paid_on
 * @property int $amount_minor
 * @property string|null $note
 * @property string $recorded_by
 */
#[Fillable(['invoice_id', 'paid_on', 'amount_minor', 'note', 'recorded_by'])]
class InvoicePayment extends Model
{
    use BelongsToTenant, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payments do not change.'));
        static::deleting(fn () => throw new LogicException('Payments are not deleted.'));
    }

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount_minor' => 'integer'];
    }
}

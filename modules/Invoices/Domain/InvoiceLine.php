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
use LogicException;

/**
 * A line of an invoice. Lines change only while it is a draft.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property int $position
 * @property string $description
 * @property int|null $minutes
 * @property string|null $quantity
 * @property string $unit
 * @property string $unit_price
 * @property string $rate_class
 * @property int $net_minor
 */
#[Fillable(['invoice_id', 'position', 'description', 'minutes', 'quantity', 'unit', 'unit_price', 'rate_class', 'net_minor'])]
class InvoiceLine extends Model
{
    use BelongsToTenant, HasUlids;

    protected static function booted(): void
    {
        $draftOnly = function (InvoiceLine $line): void {
            if (Invoice::query()->whereKey($line->invoice_id)->value('status') !== 'draft') {
                throw new LogicException('Lines of finalized invoices do not change.');
            }
        };
        static::creating($draftOnly);
        static::updating($draftOnly);
        static::deleting($draftOnly);
    }

    protected function casts(): array
    {
        return ['position' => 'integer', 'minutes' => 'integer', 'quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'net_minor' => 'integer'];
    }
}

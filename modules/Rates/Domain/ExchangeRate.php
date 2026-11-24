<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A stored exchange rate. It is never changed; a later day brings a new one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $from_currency
 * @property string $to_currency
 * @property string $rate
 * @property CarbonInterface $rate_date
 * @property string $created_by
 */
#[Fillable(['from_currency', 'to_currency', 'rate', 'rate_date', 'created_by'])]
class ExchangeRate extends Model
{
    use BelongsToTenant, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Exchange rates do not change.'));
        static::deleting(fn () => throw new LogicException('Exchange rates are not deleted.'));
    }

    protected function casts(): array
    {
        return ['rate' => 'decimal:10', 'rate_date' => 'date'];
    }
}

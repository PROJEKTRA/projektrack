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
 * The frozen value (sales or cost) of one approved time entry.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $kind
 * @property string $time_entry_id
 * @property string|null $rate_rule_id
 * @property string $source
 * @property string $rate
 * @property string $currency
 * @property int $minutes_valued
 * @property int $amount_minor
 * @property CarbonInterface $valued_at
 * @property string|null $rule_rate
 * @property string|null $rule_currency
 * @property string|null $fx_rate
 */
#[Fillable(['kind', 'time_entry_id', 'rate_rule_id', 'source', 'rate', 'currency', 'minutes_valued', 'amount_minor', 'valued_at', 'rule_rate', 'rule_currency', 'fx_rate'])]
class Valuation extends Model
{
    use BelongsToTenant, HasUlids;

    protected $table = 'time_entry_valuations';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Valuations are frozen.'));
        static::deleting(fn () => throw new LogicException('Valuations are frozen.'));
    }

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'minutes_valued' => 'integer', 'amount_minor' => 'integer', 'valued_at' => 'datetime'];
    }
}

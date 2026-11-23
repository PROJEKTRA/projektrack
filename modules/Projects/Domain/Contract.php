<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A contract of a project with its rounding rule (S-0941).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $number
 * @property string $contract_type
 * @property string $status
 * @property CarbonInterface|null $start_date
 * @property CarbonInterface|null $end_date
 * @property int|null $budget_minutes
 * @property int $rounding_minutes
 * @property int $minimum_minutes
 */
#[Fillable(['project_id', 'number', 'contract_type', 'status', 'start_date', 'end_date', 'budget_minutes', 'rounding_minutes', 'minimum_minutes'])]
class Contract extends Model
{
    use BelongsToTenant, HasUlids;

    public const array TYPES = ['framework', 'call_off', 'time_and_material', 'fixed_price', 'hour_package', 'retainer', 'maintenance'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'budget_minutes' => 'integer', 'rounding_minutes' => 'integer', 'minimum_minutes' => 'integer'];
    }
}

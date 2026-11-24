<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A threshold of a contract budget that was told.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property int $threshold_pct
 */
#[Fillable(['contract_id', 'threshold_pct'])]
class ContractBudgetAlert extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['threshold_pct' => 'integer'];
    }
}

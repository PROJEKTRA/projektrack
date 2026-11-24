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

/**
 * An hourly rate (sales or cost) for a scope and a period.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $kind
 * @property string $scope
 * @property string|null $scope_id
 * @property string $rate
 * @property string $currency
 * @property CarbonInterface $valid_from
 * @property CarbonInterface|null $valid_to
 */
#[Fillable(['kind', 'scope', 'scope_id', 'rate', 'currency', 'valid_from', 'valid_to'])]
class RateRule extends Model
{
    use BelongsToTenant, HasUlids;

    /** Most specific first (ADR-0004 section 12). */
    public const array SCOPES = ['work_package', 'contract', 'project', 'company', 'user', 'job_role', 'tenant'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'valid_from' => 'date', 'valid_to' => 'date'];
    }
}

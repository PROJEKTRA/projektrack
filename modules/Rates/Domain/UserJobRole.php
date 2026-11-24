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
 * The job role of a user in a period.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $job_role_id
 * @property CarbonInterface $valid_from
 * @property CarbonInterface|null $valid_to
 */
#[Fillable(['user_id', 'job_role_id', 'valid_from', 'valid_to'])]
class UserJobRole extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date'];
    }
}

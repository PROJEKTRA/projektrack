<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * The running timer of a user (S-0946).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $work_package_id
 * @property CarbonInterface $started_at
 */
#[Fillable(['user_id', 'work_package_id', 'started_at'])]
class Timer extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['started_at' => 'datetime'];
    }
}

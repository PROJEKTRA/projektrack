<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A month that is locked or was reopened (S-0948).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $month
 * @property bool $locked
 */
#[Fillable(['month', 'locked'])]
class TimePeriod extends Model
{
    use BelongsToTenant, HasUlids;

    public static function isLocked(string $date): bool
    {
        return self::query()->where('month', substr($date, 0, 7))->where('locked', true)->exists();
    }

    protected function casts(): array
    {
        return ['locked' => 'boolean'];
    }
}

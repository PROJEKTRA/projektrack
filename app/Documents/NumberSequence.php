<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Documents;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $document_type
 * @property string $period_key
 * @property int $next_value
 */
#[Fillable(['document_type', 'period_key', 'next_value'])]
class NumberSequence extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }
}

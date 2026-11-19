<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stored setting value (S-0912). Only values that differ from the schema
 * default are stored; the schema (settings.schema.json) defines the rest.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $scope_type
 * @property string $scope_id
 * @property string $key
 * @property mixed $value
 * @property int $lock_version
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['scope_type', 'scope_id', 'key', 'value', 'lock_version', 'updated_by'])]
class Setting extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'lock_version' => 'integer',
        ];
    }
}

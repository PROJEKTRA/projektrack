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
 * How a tenant differs from the shipped languages (S-0921): a deactivated
 * shipped language, or the tenant's own language copied from another one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $locale
 * @property string|null $name
 * @property string $direction
 * @property string|null $based_on
 * @property bool $is_custom
 * @property bool $is_active
 * @property int $revision
 * @property string|null $changed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['locale', 'name', 'direction', 'based_on', 'is_custom', 'is_active', 'revision', 'changed_by'])]
class TenantLanguage extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'is_active' => 'boolean',
            'revision' => 'integer',
        ];
    }
}

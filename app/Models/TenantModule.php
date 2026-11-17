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
 * Whether a module is switched on for a tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $module_key
 * @property bool $enabled
 * @property Carbon|null $enabled_at
 * @property Carbon|null $disabled_at
 * @property string|null $changed_by
 */
#[Fillable(['module_key', 'enabled', 'enabled_at', 'disabled_at', 'changed_by'])]
class TenantModule extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }
}

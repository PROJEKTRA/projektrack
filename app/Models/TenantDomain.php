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
 * A host name a tenant is served at. Tenant-owned; only the tenant resolver
 * reads it without a tenant context, because that is how the context is found.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $host
 * @property string $kind
 * @property bool $is_primary
 * @property Carbon|null $verified_at
 */
#[Fillable(['host', 'kind', 'is_primary', 'verified_at'])]
class TenantDomain extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}

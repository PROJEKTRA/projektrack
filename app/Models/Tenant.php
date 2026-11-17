<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A company using the instance. Instance-scoped: tenants are the root that
 * every tenant-owned row points to. Per-tenant defaults (language, currency,
 * time zone, country) live here; branding, numbering, tax and bank details
 * live in their own tenant-owned tables as their stories arrive.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $status
 * @property string $country_code
 * @property string $default_locale
 * @property string $base_currency
 * @property string $timezone
 * @property string $region
 * @property string $isolation_mode
 * @property string $edition
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'name', 'status', 'country_code', 'default_locale', 'base_currency', 'timezone', 'region', 'edition'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids;

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

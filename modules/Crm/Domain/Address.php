<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Domain;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A postal address of a company (billing) or of a site.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $owner_type
 * @property string $owner_id
 * @property string $kind
 * @property string $line1
 * @property string|null $line2
 * @property string|null $postal_code
 * @property string $city
 * @property string|null $region
 * @property string $country_code
 */
#[Fillable(['owner_type', 'owner_id', 'kind', 'line1', 'line2', 'postal_code', 'city', 'region', 'country_code'])]
class Address extends Model
{
    use BelongsToTenant, HasUlids;

    public const array FIELDS = ['line1', 'line2', 'postal_code', 'city', 'region', 'country_code'];
}

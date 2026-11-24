<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * How a company is taxed in a period. Documents copy it, so a later change never alters them.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $tax_status
 * @property string $country_code
 * @property string|null $vat_id
 * @property string|null $tax_number
 * @property CarbonInterface|null $valid_from
 * @property CarbonInterface|null $valid_to
 */
#[Fillable(['company_id', 'tax_status', 'country_code', 'vat_id', 'tax_number', 'valid_from', 'valid_to'])]
class CompanyTaxProfile extends Model
{
    use BelongsToTenant, HasUlids;

    public const array STATUSES = ['domestic', 'eu_business', 'non_eu_business', 'private', 'exempt', 'public_sector'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date'];
    }
}

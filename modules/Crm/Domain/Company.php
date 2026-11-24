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
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer, prospect or partner of the tenant (S-0939).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $number
 * @property string $name
 * @property string|null $legal_name
 * @property string $kind
 * @property string $status
 * @property string $document_locale
 * @property string $currency
 * @property int $payment_days
 * @property string $einvoice_format
 * @property string|null $leitweg_id
 */
#[Fillable(['number', 'name', 'legal_name', 'kind', 'status', 'document_locale', 'currency', 'payment_days', 'einvoice_format', 'leitweg_id'])]
class Company extends Model
{
    use BelongsToTenant, HasUlids;

    public const array KINDS = ['customer', 'prospect', 'partner', 'supplier'];

    public const array STATUSES = ['active', 'archived'];

    public const array EINVOICE_FORMATS = ['pdf', 'zugferd', 'xrechnung_cii', 'xrechnung_ubl'];

    /** @return HasMany<CompanyTaxProfile, $this> */
    public function taxProfiles(): HasMany
    {
        return $this->hasMany(CompanyTaxProfile::class);
    }

    /** @return HasMany<CompanySite, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(CompanySite::class)->orderBy('name')->orderBy('id');
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderBy('last_name')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['payment_days' => 'integer'];
    }
}

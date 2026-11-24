<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

namespace Modules\Licensing\Domain;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A plan that is sold: data, not code.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $key
 * @property string $name
 * @property string $edition
 * @property int $term_months
 * @property int $price_lock_months
 * @property string $full_price
 * @property string $light_price
 * @property string $onboarding_price
 * @property string $currency
 * @property bool $active
 */
#[Fillable(['key', 'name', 'edition', 'term_months', 'price_lock_months', 'full_price', 'light_price', 'onboarding_price', 'currency', 'active'])]
class LicensePlan extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['term_months' => 'integer', 'price_lock_months' => 'integer', 'full_price' => 'decimal:4', 'light_price' => 'decimal:4', 'onboarding_price' => 'decimal:4', 'active' => 'boolean'];
    }
}

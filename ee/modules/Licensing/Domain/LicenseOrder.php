<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

namespace Modules\Licensing\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A sold license: pending payment, paid, active, to review or cancelled.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $number
 * @property string $license_plan_id
 * @property string $company_id
 * @property string|null $invoice_id
 * @property string $license_id
 * @property int $serial
 * @property string $status
 * @property int $full_seats
 * @property int $light_seats
 * @property string $instance_id
 * @property string $domain
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 * @property CarbonInterface|null $pending_until
 * @property CarbonInterface|null $reminded_at
 * @property string|null $token
 * @property int|null $issued_serial
 * @property string $customer_name
 * @property string $full_price
 * @property string $light_price
 * @property string $onboarding_price
 * @property string $currency
 * @property int $net_minor
 * @property CarbonInterface $price_locked_until
 */
#[Fillable(['number', 'license_plan_id', 'company_id', 'invoice_id', 'license_id', 'serial', 'status', 'full_seats', 'light_seats', 'instance_id', 'domain', 'starts_on', 'ends_on', 'pending_until', 'reminded_at', 'token', 'issued_serial', 'customer_name', 'full_price', 'light_price', 'onboarding_price', 'currency', 'net_minor', 'price_locked_until'])]
class LicenseOrder extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['serial' => 'integer', 'issued_serial' => 'integer', 'net_minor' => 'integer', 'price_locked_until' => 'date', 'full_price' => 'decimal:4', 'light_price' => 'decimal:4', 'onboarding_price' => 'decimal:4', 'full_seats' => 'integer', 'light_seats' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'pending_until' => 'datetime', 'reminded_at' => 'datetime'];
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use App\Authorization\ProjectScoped;
use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A project for a customer (S-0940).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $number
 * @property string $name
 * @property string $company_id
 * @property string $status
 * @property string $billing_model
 * @property CarbonInterface|null $start_date
 * @property CarbonInterface|null $end_date
 * @property int|null $budget_minutes
 * @property int|null $budget_minor
 * @property string $currency
 * @property int $amber_percent
 */
#[Fillable(['number', 'name', 'company_id', 'status', 'billing_model', 'start_date', 'end_date', 'budget_minutes', 'budget_minor', 'currency', 'amber_percent'])]
class Project extends Model implements ProjectScoped
{
    use BelongsToTenant, HasUlids;

    public const array STATUSES = ['active', 'on_hold', 'closed'];

    public const array BILLING_MODELS = ['time_and_material', 'fixed_price', 'retainer', 'hour_package', 'mixed', 'internal'];

    public function scopedProjectId(): string
    {
        return $this->id;
    }

    /** green, amber from the threshold of the hour budget, red above the budget. */
    public function health(int $bookedMinutes): string
    {
        if ($this->budget_minutes === null || $this->budget_minutes === 0) {
            return 'green';
        }

        return match (true) {
            $bookedMinutes > $this->budget_minutes => 'red',
            $bookedMinutes * 100 >= $this->budget_minutes * $this->amber_percent => 'amber',
            default => 'green',
        };
    }

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'budget_minutes' => 'integer', 'budget_minor' => 'integer', 'amber_percent' => 'integer'];
    }
}

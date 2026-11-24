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
 * A node of the work breakdown structure (S-0942).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string|null $parent_id
 * @property string|null $contract_id
 * @property string $wbs_code
 * @property string $path
 * @property int $sort_order
 * @property string $name
 * @property string $status
 * @property int|null $planned_minutes
 * @property CarbonInterface|null $start_date
 * @property CarbonInterface|null $end_date
 * @property int $percent_complete
 * @property bool $billable
 * @property string|null $service_category
 */
#[Fillable(['project_id', 'parent_id', 'contract_id', 'wbs_code', 'path', 'sort_order', 'name', 'status', 'planned_minutes', 'start_date', 'end_date', 'percent_complete', 'billable', 'service_category'])]
class WorkPackage extends Model implements ProjectScoped
{
    use BelongsToTenant, HasUlids;

    public const array STATUSES = ['open', 'closed'];

    public const array CATEGORIES = ['project_management', 'consulting', 'development', 'operations', 'support', 'daily'];

    public const int MAX_DEPTH = 6;

    public function scopedProjectId(): string
    {
        return $this->project_id;
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'planned_minutes' => 'integer', 'start_date' => 'date', 'end_date' => 'date', 'percent_complete' => 'integer', 'billable' => 'boolean'];
    }
}

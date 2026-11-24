<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use App\Authorization\ProjectScoped;
use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Time of one user on a work package on a day, in whole minutes (S-0944).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $project_id
 * @property string $work_package_id
 * @property CarbonInterface $work_date
 * @property int $duration_minutes
 * @property int $billable_minutes
 * @property string|null $description
 * @property string $status
 * @property CarbonInterface|null $submitted_at
 * @property string|null $approved_by
 * @property CarbonInterface|null $approved_at
 * @property string|null $reject_reason
 * @property string|null $corrects_id
 * @property string|null $correction_reason
 */
#[Fillable(['user_id', 'project_id', 'work_package_id', 'work_date', 'duration_minutes', 'billable_minutes', 'description', 'status', 'submitted_at', 'approved_by', 'approved_at', 'reject_reason', 'corrects_id', 'correction_reason'])]
class TimeEntry extends Model implements ProjectScoped
{
    use BelongsToTenant, HasUlids;

    /** States in which the owner may change the entry. */
    public const array EDITABLE = ['draft', 'rejected'];

    public function scopedProjectId(): string
    {
        return $this->project_id;
    }

    protected static function booted(): void
    {
        // Approved time is never changed and never deleted: corrections only.
        static::updating(function (TimeEntry $entry): void {
            if ($entry->getOriginal('status') === 'approved') {
                throw new LogicException('Approved time entries cannot change.');
            }
        });
        static::deleting(function (TimeEntry $entry): void {
            if (! in_array($entry->getOriginal('status'), self::EDITABLE, true)) {
                throw new LogicException('Only drafts and rejected entries can be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return ['work_date' => 'date', 'duration_minutes' => 'integer', 'billable_minutes' => 'integer', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}

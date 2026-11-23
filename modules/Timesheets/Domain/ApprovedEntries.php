<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use App\Models\User;
use Modules\Timesheets\Contracts\ApprovedTime;

final class ApprovedEntries implements ApprovedTime
{
    public function between(string $projectId, string $from, string $to): array
    {
        $entries = TimeEntry::query()->where('project_id', $projectId)->where('status', 'approved')
            ->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->orderBy('work_date')->orderBy('id')->get();
        $users = User::query()->whereIn('id', $entries->pluck('user_id')->unique()->values())->get()->keyBy('id');

        return array_values($entries->map(fn (TimeEntry $e): array => [
            'id' => $e->id, 'date' => $e->work_date->toDateString(), 'user_id' => $e->user_id, 'user' => $users->get($e->user_id)->name ?? '',
            'work_package_id' => $e->work_package_id, 'project_id' => $e->project_id, 'minutes' => $e->duration_minutes, 'billable_minutes' => $e->billable_minutes,
            'description' => $e->description ?? $e->correction_reason, 'correction' => $e->corrects_id !== null,
        ])->all());
    }

    public function openCount(string $projectId, string $from, string $to): int
    {
        return TimeEntry::query()->where('project_id', $projectId)->where('status', '!=', 'approved')->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->count();
    }
}

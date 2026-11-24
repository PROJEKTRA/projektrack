<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use App\Models\User;
use Carbon\CarbonImmutable;
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
            'description' => $e->description ?? $e->correction_reason, 'correction' => $e->corrects_id !== null, 'corrects' => $e->corrects_id,
        ])->all());
    }

    public function sums(string $from, string $to): array
    {
        $result = [];
        // Plain comparisons, so the index on the date is used; no models for thousands of rows.
        $rows = TimeEntry::query()->where('status', 'approved')
            ->where('work_date', '>=', $from)->where('work_date', '<', CarbonImmutable::parse($to, 'UTC')->addDay()->toDateString())
            ->toBase()->get(['id', 'project_id', 'duration_minutes', 'billable_minutes']);
        foreach ($rows as $row) {
            if (is_string($row->id) && is_string($row->project_id) && is_numeric($row->duration_minutes) && is_numeric($row->billable_minutes)) {
                $result[$row->project_id][] = ['id' => $row->id, 'minutes' => (int) $row->duration_minutes, 'billable_minutes' => (int) $row->billable_minutes];
            }
        }

        return $result;
    }

    public function openCount(string $projectId, string $from, string $to): int
    {
        return TimeEntry::query()->where('project_id', $projectId)->where('status', '!=', 'approved')->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->count();
    }
}

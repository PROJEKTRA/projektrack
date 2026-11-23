<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use Modules\Projects\Contracts\BookedTime;

/**
 * Booked minutes rolled up, whatever the state of the entries.
 */
final class EntryTotals implements BookedTime
{
    public function minutesByProject(array $projectIds): array
    {
        return $this->sum('project_id', $projectIds);
    }

    public function minutesByWorkPackage(string $projectId): array
    {
        return $this->sum('work_package_id', [$projectId]);
    }

    /**
     * @param  list<string>  $projectIds
     * @return array<string, int>
     */
    private function sum(string $by, array $projectIds): array
    {
        $totals = [];
        $rows = TimeEntry::query()->whereIn('project_id', $projectIds)->select($by)->selectRaw('sum(duration_minutes) as minutes')->groupBy($by)->toBase()->get();
        foreach ($rows as $row) {
            $owner = $row->{$by} ?? null;
            $minutes = $row->minutes ?? null;
            if (is_string($owner) && is_numeric($minutes)) {
                $totals[$owner] = (int) $minutes;
            }
        }

        return $totals;
    }
}

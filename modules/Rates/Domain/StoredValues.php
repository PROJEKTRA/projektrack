<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Domain;

use App\Models\User;
use Modules\Rates\Contracts\TimeValues;
use Modules\Timesheets\Contracts\TimeApproved;

final class StoredValues implements TimeValues
{
    public function __construct(private readonly Rates $rates) {}

    public function valueMissing(array $rows, User $actor): int
    {
        // Originals first: a correction takes the rate of its entry.
        usort($rows, fn (array $a, array $b): int => ($a['corrects'] === null ? 0 : 1) <=> ($b['corrects'] === null ? 0 : 1));

        return $this->rates->valueMissing(array_map(fn (array $r): TimeApproved => new TimeApproved($r['id'], $r['user_id'], $r['work_package_id'], $r['project_id'], $r['date'], $r['billable_minutes'], $r['minutes'], $r['corrects']), $rows), $actor);
    }

    public function of(array $entryIds): array
    {
        $values = [];
        foreach (array_chunk($entryIds, 500) as $ids) {
            foreach (Valuation::query()->where('kind', 'sales')->whereIn('time_entry_id', $ids)->get() as $v) {
                $values[$v->time_entry_id] = ['rate' => $v->rate, 'currency' => $v->currency, 'minutes' => $v->minutes_valued, 'amount_minor' => $v->amount_minor, 'source' => $v->source];
            }
        }

        return $values;
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Domain;

use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Timesheets\Contracts\ApprovedTime;

/**
 * Value, cost and contribution margin of approved time (S-0950). Entries
 * without a value or a cost are counted, never guessed.
 */
final class Margins
{
    public function __construct(
        private readonly ProjectDirectory $projects,
        private readonly ApprovedTime $time,
    ) {}

    /** @return list<array{id: string, name: string, number: string, currency: string, minutes: int, sales_minor: int, cost_minor: int, margin_minor: int, without_sales: int, without_cost: int}> */
    public function between(string $from, string $to): array
    {
        $result = [];
        foreach ($this->projects->active() as $project) {
            $rows = $this->time->between($project['id'], $from, $to);
            if ($rows === []) {
                continue;
            }
            $sums = ['sales' => 0, 'cost' => 0];
            $found = ['sales' => [], 'cost' => []];
            foreach (array_chunk(array_column($rows, 'id'), 500) as $ids) {
                foreach (Valuation::query()->whereIn('time_entry_id', $ids)->get() as $v) {
                    $kind = $v->kind === 'cost' ? 'cost' : 'sales';
                    // One currency per project; a value in another one cannot be added.
                    if ($v->currency === $project['currency']) {
                        $sums[$kind] += $v->amount_minor;
                        $found[$kind][$v->time_entry_id] = true;
                    }
                }
            }
            $result[] = [
                'id' => $project['id'], 'name' => $project['name'], 'number' => $project['number'], 'currency' => $project['currency'],
                'minutes' => array_sum(array_column($rows, 'minutes')), 'sales_minor' => $sums['sales'], 'cost_minor' => $sums['cost'], 'margin_minor' => $sums['sales'] - $sums['cost'],
                'without_sales' => count(array_filter($rows, fn (array $r): bool => $r['billable_minutes'] !== 0 && ! isset($found['sales'][$r['id']]))),
                'without_cost' => count(array_filter($rows, fn (array $r): bool => ! isset($found['cost'][$r['id']]))),
            ];
        }

        return $result;
    }
}

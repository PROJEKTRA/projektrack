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

    /** @return list<array{id: string, name: string, number: string, currency: string, minutes: int, sales_minor: int, cost_minor: int, margin_minor: int|null, without_sales: int, without_cost: int}> */
    public function between(string $from, string $to): array
    {
        $result = [];
        $time = $this->time->sums($from, $to);
        $values = $this->values($time === [] ? [] : array_column(array_merge(...array_values($time)), 'id'));
        foreach ($this->projects->all() as $project) {
            $rows = $time[$project['id']] ?? [];
            if ($rows === []) {
                continue;
            }
            $sums = ['sales' => 0, 'cost' => 0];
            $found = ['sales' => [], 'cost' => []];
            foreach ($rows as $row) {
                foreach ($values[$row['id']] ?? [] as [$kind, $currency, $minor]) {
                    // One currency per project; a value in another one cannot be added.
                    if ($currency === $project['currency']) {
                        $sums[$kind] += $minor;
                        $found[$kind][$row['id']] = true;
                    }
                }
            }
            $withoutSales = count(array_filter($rows, fn (array $r): bool => $r['billable_minutes'] !== 0 && ! isset($found['sales'][$r['id']])));
            $withoutCost = count(array_filter($rows, fn (array $r): bool => ! isset($found['cost'][$r['id']])));
            // Hours times rate is no revenue at a fixed price, and a sum with gaps is no margin.
            $known = in_array($project['billing_model'], ['time_and_material', 'mixed'], true) && $withoutSales === 0 && $withoutCost === 0;
            $result[] = [
                'id' => $project['id'], 'name' => $project['name'], 'number' => $project['number'], 'currency' => $project['currency'],
                'minutes' => array_sum(array_column($rows, 'minutes')), 'sales_minor' => $sums['sales'], 'cost_minor' => $sums['cost'], 'margin_minor' => $known ? $sums['sales'] - $sums['cost'] : null,
                'without_sales' => $withoutSales, 'without_cost' => $withoutCost,
            ];
        }

        return $result;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, list<array{'cost'|'sales', string, int}>> by time entry
     */
    private function values(array $ids): array
    {
        $values = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (Valuation::query()->whereIn('time_entry_id', $chunk)->toBase()->get(['time_entry_id', 'kind', 'currency', 'amount_minor']) as $v) {
                if (is_string($v->time_entry_id) && is_string($v->currency) && is_numeric($v->amount_minor)) {
                    $values[$v->time_entry_id][] = [$v->kind === 'cost' ? 'cost' : 'sales', $v->currency, (int) $v->amount_minor];
                }
            }
        }

        return $values;
    }
}

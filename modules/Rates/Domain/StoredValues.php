<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Domain;

use Modules\Rates\Contracts\TimeValues;

final class StoredValues implements TimeValues
{
    public function of(array $entryIds): array
    {
        $values = [];
        foreach (array_chunk($entryIds, 500) as $ids) {
            foreach (Valuation::query()->whereIn('time_entry_id', $ids)->get() as $v) {
                $values[$v->time_entry_id] = ['rate' => $v->rate, 'currency' => $v->currency, 'minutes' => $v->minutes_valued, 'amount_minor' => $v->amount_minor, 'source' => $v->source];
            }
        }

        return $values;
    }
}

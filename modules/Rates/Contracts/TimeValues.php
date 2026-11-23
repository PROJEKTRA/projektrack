<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Contracts;

use App\Models\User;

/**
 * Frozen values of approved time, for service reports and invoices.
 */
interface TimeValues
{
    /**
     * Value approved time without a value, by the rates of its days.
     *
     * @param  list<array{id: string, user_id: string, work_package_id: string, project_id: string, date: string, billable_minutes: int, corrects: string|null}>  $rows
     */
    public function valueMissing(array $rows, User $actor): int;

    /**
     * @param  list<string>  $entryIds
     * @return array<string, array{rate: string, currency: string, minutes: int, amount_minor: int, source: string}> entry id => value; entries without a rate are missing
     */
    public function of(array $entryIds): array;
}

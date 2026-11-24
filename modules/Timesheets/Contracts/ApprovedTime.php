<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Contracts;

/**
 * Approved time, corrections included, for service reports and invoices.
 *
 * @phpstan-type ApprovedRow array{id: string, date: string, user_id: string, user: string, work_package_id: string, project_id: string, minutes: int, billable_minutes: int, description: string|null, correction: bool, corrects: string|null}
 */
interface ApprovedTime
{
    /** @return list<ApprovedRow> in date order */
    public function between(string $projectId, string $from, string $to): array;

    /** How many entries in the period are not approved yet. */
    public function openCount(string $projectId, string $from, string $to): int;
}

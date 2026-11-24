<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Contracts;

/**
 * Told by time tracking after approvals; warns at the thresholds of the budget.
 */
interface BudgetWatch
{
    /** @return list<string> work package ids of the contract */
    public function packagesOf(string $contractId): array;

    public function approved(string $contractId, int $minutes): void;
}

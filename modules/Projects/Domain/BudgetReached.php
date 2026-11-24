<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use App\Notifications\AppNotice;

/**
 * A contract budget reached a threshold (S-0941).
 */
class BudgetReached extends AppNotice
{
    public function __construct(
        private readonly string $contract,
        private readonly int $threshold,
        private readonly string $projectId,
    ) {}

    public function type(): string
    {
        return 'projects.budget';
    }

    public function message(): string
    {
        return 'projects:notice.budget';
    }

    /** @return array<string, scalar> */
    public function arguments(): array
    {
        return ['contract' => $this->contract, 'percent' => $this->threshold];
    }

    public function url(): string
    {
        return '/projects/'.$this->projectId;
    }
}

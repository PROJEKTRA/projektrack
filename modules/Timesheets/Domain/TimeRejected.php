<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use App\Notifications\AppNotice;

/**
 * Tells a person that submitted time came back, with the reason (S-0947).
 */
class TimeRejected extends AppNotice
{
    public function __construct(
        private readonly string $reason,
        private readonly string $date,
    ) {}

    public function type(): string
    {
        return 'timesheets.rejected';
    }

    public function message(): string
    {
        return 'timesheets:notice.rejected';
    }

    /** @return array<string, scalar> */
    public function arguments(): array
    {
        return ['date' => $this->date, 'reason' => $this->reason];
    }

    public function url(): string
    {
        return '/timesheets?date='.$this->date;
    }
}

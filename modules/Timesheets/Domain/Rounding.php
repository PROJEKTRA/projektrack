<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

/**
 * Billable minutes by the rule of the contract: at least the minimum, rounded
 * up to whole steps. No time stays no time.
 */
final class Rounding
{
    public static function billable(int $minutes, int $step, int $minimum): int
    {
        if ($minutes <= 0) {
            return 0;
        }
        $step = max(1, $step);

        return intdiv(max($minutes, $minimum) + $step - 1, $step) * $step;
    }
}

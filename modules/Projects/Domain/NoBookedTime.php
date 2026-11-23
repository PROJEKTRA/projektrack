<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use Modules\Projects\Contracts\BookedTime;

final class NoBookedTime implements BookedTime
{
    public function minutesByProject(array $projectIds): array
    {
        return [];
    }

    public function minutesByWorkPackage(string $projectId): array
    {
        return [];
    }
}

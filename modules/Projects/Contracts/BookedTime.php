<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Contracts;

/**
 * Booked working time, answered by the time tracking module.
 */
interface BookedTime
{
    /**
     * @param  list<string>  $projectIds
     * @return array<string, int> project id => minutes
     */
    public function minutesByProject(array $projectIds): array;

    /** @return array<string, int> work package id => minutes, in one project */
    public function minutesByWorkPackage(string $projectId): array;
}

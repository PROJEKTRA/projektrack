<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Contracts;

/**
 * Time was approved or corrected. Raised inside the transaction, so whatever
 * a listener stores stands or falls with the approval.
 */
final readonly class TimeApproved
{
    /** @param string|null $corrects id of the approved entry this one corrects */
    public function __construct(
        public string $entryId,
        public string $userId,
        public string $workPackageId,
        public string $projectId,
        public string $date,
        public int $billableMinutes,
        public int $minutes,
        public ?string $corrects,
    ) {}
}

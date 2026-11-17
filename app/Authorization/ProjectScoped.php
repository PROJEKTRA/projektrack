<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

/**
 * Implemented by anything that lives in a project (the project itself, a time
 * entry, a work package). Permission checks against such an object also take
 * the user's project roles in that project into account.
 */
interface ProjectScoped
{
    public function scopedProjectId(): ?string;
}

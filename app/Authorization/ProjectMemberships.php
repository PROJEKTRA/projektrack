<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use App\Models\User;

/**
 * Contract the projects module implements (project_members). The core never
 * reads that module's tables; it asks through this interface.
 *
 * Implementations return project role ids of the current tenant only.
 */
interface ProjectMemberships
{
    /**
     * @return list<string> project role ids of $user in $projectId
     */
    public function projectRoleIds(User $user, string $projectId): array;

    /**
     * @return list<string> project role ids of $user across all projects
     */
    public function allProjectRoleIds(User $user): array;
}

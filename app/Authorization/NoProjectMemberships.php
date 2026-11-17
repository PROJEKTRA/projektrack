<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use App\Models\User;

/**
 * Bound while the projects module is not installed: nobody has project roles.
 */
final class NoProjectMemberships implements ProjectMemberships
{
    public function projectRoleIds(User $user, string $projectId): array
    {
        return [];
    }

    public function allProjectRoleIds(User $user): array
    {
        return [];
    }
}

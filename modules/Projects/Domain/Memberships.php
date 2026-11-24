<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use App\Authorization\ProjectMemberships;
use App\Models\User;
use App\Support\Lists;
use App\Tenancy\TenantContext;

/**
 * Project roles of a user, from project_members of the current tenant.
 */
final class Memberships implements ProjectMemberships
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function projectRoleIds(User $user, string $projectId): array
    {
        return $this->ids($user, $projectId);
    }

    public function allProjectRoleIds(User $user): array
    {
        return $this->ids($user, null);
    }

    /** @return list<string> */
    private function ids(User $user, ?string $projectId): array
    {
        $today = now($this->tenant->current()->timezone)->toDateString();
        $query = ProjectMember::query()->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('joined_on')->orWhereDate('joined_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('left_on')->orWhereDate('left_on', '>=', $today));
        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return Lists::strings($query->distinct()->pluck('project_role_id'));
    }
}

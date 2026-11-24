<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use App\Audit\AuditLog;
use App\Authorization\Permissions;
use App\Authorization\RoleManager;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class Projects
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Permissions $permissions,
        private readonly AuditLog $audit,
        private readonly RoleManager $roles,
    ) {}

    /** @return Builder<Project> projects the user may open */
    public function visibleTo(User $user): Builder
    {
        $query = Project::query();
        if ($this->permissions->allows($user, 'projects.view')) {
            return $query;
        }
        $ids = ProjectMember::query()->where('user_id', $user->id)->distinct()->get(['project_id'])
            ->map(fn (ProjectMember $m): string => $m->project_id)
            ->filter(fn (string $id): bool => $this->permissions->allows($user, 'projects.view', $id));

        return $query->whereIn('id', $ids->values());
    }

    /** @param array<string, mixed> $values */
    public function create(array $values, User $actor, string $leadRoleId): Project
    {
        return DB::transaction(function () use ($values, $actor, $leadRoleId): Project {
            Tenant::query()->whereKey($this->tenant->id())->lockForUpdate()->first();
            $last = Project::query()->orderByDesc('number')->first()->number ?? 'P-0';
            $project = Project::query()->create([...$values, 'number' => sprintf('P-%05d', (int) substr($last, 2) + 1), 'status' => 'active']);
            // Whoever starts a project leads it.
            ProjectMember::query()->create(['project_id' => $project->id, 'user_id' => $actor->id, 'project_role_id' => $leadRoleId]);
            $this->permissions->flush();
            $this->roles->refreshUserType($actor);
            $this->audit->record('projects.project.created', $project, ['number' => $project->number], $actor);

            return $project;
        }, 3);
    }

    /** @param array<string, mixed> $values */
    public function update(Project $project, array $values, User $actor): void
    {
        $project->fill($values);
        $fields = array_keys($project->getDirty());
        if ($fields === []) {
            return;
        }
        DB::transaction(function () use ($project, $fields, $actor): void {
            $project->save();
            $this->audit->record('projects.project.updated', $project, ['fields' => $fields], $actor);
        });
    }
}

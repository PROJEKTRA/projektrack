<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Http;

use App\Models\ProjectRole;
use App\Models\User;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Projects\Contracts\BookedTime;
use Modules\Projects\Domain\Contract;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\ProjectMember;
use Modules\Projects\Domain\WorkPackage;

/**
 * What a user sees of projects. Money only with projects.budget.view.
 */
final class ProjectView
{
    public function __construct(
        private readonly CompanyDirectory $companies,
        private readonly BookedTime $time,
    ) {}

    /**
     * @param  list<Project>  $projects
     * @return list<array<string, mixed>>
     */
    public function rows(array $projects): array
    {
        $names = $this->companies->names(array_map(fn (Project $p): string => $p->company_id, $projects));
        $booked = $this->time->minutesByProject(array_map(fn (Project $p): string => $p->id, $projects));

        return array_map(fn (Project $p): array => [
            ...$p->only(['id', 'number', 'name', 'status', 'billing_model', 'budget_minutes']),
            'company' => $names[$p->company_id] ?? '',
            'booked_minutes' => $booked[$p->id] ?? 0,
            'health' => $p->health($booked[$p->id] ?? 0),
        ], $projects);
    }

    /** @return array<string, mixed> */
    public function details(Project $project, User $user): array
    {
        $booked = $this->time->minutesByWorkPackage($project->id);
        $names = User::query()->whereIn('id', ProjectMember::query()->where('project_id', $project->id)->select('user_id'))->get()->keyBy('id');
        $roles = ProjectRole::query()->get()->keyBy('id');

        return [
            ...$this->rows([$project])[0],
            'company_id' => $project->company_id,
            'currency' => $project->currency,
            'amber_percent' => $project->amber_percent,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            ...($user->can('projects.budget.view', $project) ? ['budget_minor' => $project->budget_minor] : []),
            'members' => ProjectMember::query()->where('project_id', $project->id)->orderBy('id')->get()->map(fn (ProjectMember $m): array => [
                'id' => $m->id,
                'name' => $names->get($m->user_id)->name ?? '',
                'role' => $this->roleName($roles->get($m->project_role_id)),
                'left_on' => $m->left_on?->toDateString(),
            ])->all(),
            'contracts' => Contract::query()->where('project_id', $project->id)->orderBy('number')->get()->map(fn (Contract $c): array => [
                ...$c->only(['id', 'number', 'contract_type', 'status', 'budget_minutes', 'rounding_minutes', 'minimum_minutes']),
                'start_date' => $c->start_date?->toDateString(),
                'end_date' => $c->end_date?->toDateString(),
            ])->all(),
            'work_packages' => WorkPackage::query()->where('project_id', $project->id)->orderBy('path')->get()->map(fn (WorkPackage $p): array => [
                ...$p->only(['id', 'parent_id', 'contract_id', 'wbs_code', 'sort_order', 'name', 'status', 'planned_minutes', 'billable', 'service_category', 'percent_complete']),
                'start_date' => $p->start_date?->toDateString(),
                'end_date' => $p->end_date?->toDateString(),
                'booked_minutes' => $booked[$p->id] ?? 0,
            ])->all(),
        ];
    }

    public function roleName(?ProjectRole $role): string
    {
        return $role === null ? '' : ($role->name_string !== null ? __($role->name_string) : $role->key);
    }
}

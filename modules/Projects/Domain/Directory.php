<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Projects\Contracts\ProjectDirectory;

/**
 * @phpstan-import-type Bookable from ProjectDirectory
 */
final class Directory implements ProjectDirectory
{
    public function bookable(string $userId, string $onDate): array
    {
        return array_values($this->describe($this->open($userId, $onDate)->get()));
    }

    public function mayBook(string $userId, string $workPackageId, string $onDate): bool
    {
        return $this->open($userId, $onDate)->whereKey($workPackageId)->exists();
    }

    public function workPackages(array $ids): array
    {
        return $this->describe(WorkPackage::query()->whereIn('id', $ids)->get());
    }

    public function projects(array $ids): array
    {
        return $this->list(Project::query()->whereIn('id', $ids)->get());
    }

    public function active(): array
    {
        return array_values($this->list(Project::query()->where('status', 'active')->orderBy('name')->get()));
    }

    public function rounding(string $workPackageId): array
    {
        $contractId = WorkPackage::query()->findOrFail($workPackageId)->contract_id;
        $contract = $contractId === null ? null : Contract::query()->find($contractId);

        return ['rounding' => $contract->rounding_minutes ?? 1, 'minimum' => $contract->minimum_minutes ?? 0];
    }

    /** @return Builder<WorkPackage> */
    private function open(string $userId, string $onDate): Builder
    {
        $members = ProjectMember::query()->where('user_id', $userId)
            ->where(fn (Builder $q) => $q->whereNull('joined_on')->orWhere('joined_on', '<=', $onDate))
            ->where(fn (Builder $q) => $q->whereNull('left_on')->orWhere('left_on', '>=', $onDate));
        $projects = Project::query()->where('status', 'active')
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $onDate))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $onDate));

        return WorkPackage::query()->where('status', 'open')
            ->whereIn('project_id', $members->select('project_id'))
            ->whereIn('project_id', $projects->select('id'))
            ->orderBy('project_id')->orderBy('path');
    }

    /**
     * @param  Collection<int, WorkPackage>  $packages
     * @return array<string, Bookable>
     */
    private function describe(Collection $packages): array
    {
        $projects = Project::query()->whereIn('id', $packages->pluck('project_id')->unique()->values())->get()->keyBy('id');
        $result = [];
        foreach ($packages as $package) {
            $project = $projects->get($package->project_id);
            $result[$package->id] = [
                'id' => $package->id, 'code' => $package->wbs_code, 'name' => $package->name, 'billable' => $package->billable,
                'project_id' => $package->project_id, 'project' => $project->name ?? '', 'company_id' => $project->company_id ?? '',
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<string, array{id: string, name: string, number: string, company_id: string, currency: string}>
     */
    private function list(Collection $projects): array
    {
        $result = [];
        foreach ($projects as $p) {
            $result[$p->id] = ['id' => $p->id, 'name' => $p->name, 'number' => $p->number, 'company_id' => $p->company_id, 'currency' => $p->currency];
        }

        return $result;
    }
}

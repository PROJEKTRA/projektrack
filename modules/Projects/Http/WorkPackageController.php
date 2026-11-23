<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Http;

use App\Audit\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Projects\Domain\Contract;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\WorkBreakdown;
use Modules\Projects\Domain\WorkPackage;

class WorkPackageController extends Controller
{
    public function store(Request $request, string $project, WorkBreakdown $wbs, AuditLog $audit): RedirectResponse
    {
        $model = Project::query()->findOrFail($project);
        $values = $this->values($request, $model);
        $parent = $this->parent($request, $model);
        $package = $wbs->add($model, $values, $parent);
        $audit->record('projects.work_package.created', $package, ['code' => $package->wbs_code], $this->currentUser($request));

        return to_route('projects.show', $project);
    }

    public function update(Request $request, string $project, string $package, AuditLog $audit): RedirectResponse
    {
        $model = Project::query()->findOrFail($project);
        $row = WorkPackage::query()->where('project_id', $model->id)->findOrFail($package);
        $row->fill($this->values($request, $model));
        $fields = array_keys($row->getDirty());
        $row->save();
        $audit->record('projects.work_package.updated', $row, ['fields' => $fields], $this->currentUser($request));

        return to_route('projects.show', $project);
    }

    public function move(Request $request, string $project, string $package, WorkBreakdown $wbs): RedirectResponse
    {
        $model = Project::query()->findOrFail($project);
        $row = WorkPackage::query()->where('project_id', $model->id)->findOrFail($package);
        $request->validate(['place' => ['required', 'integer', 'between:0,9999']]);
        $wbs->move($model, $row, $this->parent($request, $model), $request->integer('place'));

        return to_route('projects.show', $project);
    }

    private function parent(Request $request, Project $project): ?string
    {
        $request->validate(['parent_id' => ['nullable', 'ulid']]);
        $id = $request->input('parent_id');

        return is_string($id) ? WorkPackage::query()->where('project_id', $project->id)->findOrFail($id)->id : null;
    }

    /** @return array<string, mixed> */
    private function values(Request $request, Project $project): array
    {
        /** @var array<string, mixed> $values */
        $values = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191'],
            'status' => ['sometimes', Rule::in(WorkPackage::STATUSES)],
            'contract_id' => ['nullable', 'ulid', function (string $attribute, mixed $value, callable $fail) use ($project): void {
                if (! Contract::query()->where('project_id', $project->id)->whereKey($value)->exists()) {
                    $fail(__('projects:errors.contract'));
                }
            }],
            'planned_minutes' => ['nullable', 'integer', 'between:0,60000000'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'percent_complete' => ['sometimes', 'integer', 'between:0,100'],
            'billable' => ['required', 'boolean'],
            'service_category' => ['nullable', Rule::in(WorkPackage::CATEGORIES)],
        ])->validate();

        return $values;
    }
}

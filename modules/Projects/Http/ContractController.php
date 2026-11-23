<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Http;

use App\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Projects\Domain\Contract;
use Modules\Projects\Domain\Project;

class ContractController extends Controller
{
    public function store(Request $request, string $project, AuditLog $audit, TenantContext $tenant): RedirectResponse
    {
        $model = Project::query()->findOrFail($project);
        $values = $this->values($request, $tenant, null);
        DB::transaction(function () use ($model, $values, $audit, $request): void {
            $contract = Contract::query()->create([...$values, 'project_id' => $model->id]);
            $audit->record('projects.contract.created', $contract, ['number' => $contract->number], $this->currentUser($request));
        });

        return to_route('projects.show', $project);
    }

    public function update(Request $request, string $project, string $contract, AuditLog $audit, TenantContext $tenant): RedirectResponse
    {
        $row = Contract::query()->where('project_id', Project::query()->findOrFail($project)->id)->findOrFail($contract);
        $row->fill($this->values($request, $tenant, $row));
        $fields = array_keys($row->getDirty());
        DB::transaction(function () use ($row, $fields, $audit, $request): void {
            $row->save();
            $audit->record('projects.contract.updated', $row, ['fields' => $fields], $this->currentUser($request));
        });

        return to_route('projects.show', $project);
    }

    /** @return array<string, mixed> */
    private function values(Request $request, TenantContext $tenant, ?Contract $contract): array
    {
        /** @var array<string, mixed> $values */
        $values = Validator::make($request->all(), [
            'number' => ['required', 'string', 'max:60', Rule::unique('contracts', 'number')->where('tenant_id', $tenant->id())->ignore($contract?->id)],
            'contract_type' => ['required', Rule::in(Contract::TYPES)],
            'status' => ['required', Rule::in(['active', 'closed'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'budget_minutes' => ['nullable', 'integer', 'between:0,60000000'],
            'rounding_minutes' => ['required', 'integer', Rule::in([1, 5, 6, 10, 15, 30, 60])],
            'minimum_minutes' => ['required', 'integer', 'between:0,480'],
        ])->validate();

        return $values;
    }
}

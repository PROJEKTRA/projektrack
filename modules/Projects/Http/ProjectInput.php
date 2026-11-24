<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Projects\Domain\Project;

final class ProjectInput
{
    public function __construct(private readonly CompanyDirectory $companies) {}

    /** @return array<string, mixed> */
    public function validate(Request $request, User $user, ?Project $project): array
    {
        $mayMoney = $user->can('projects.budget.view', $project === null ? [] : [$project]);
        abort_if($request->has('budget_minor') && ! $mayMoney, 403);
        // Who pays, and in which currency, is not for project leads to change.
        abort_if($project !== null && ! $user->can('projects.contracts.manage', [$project])
            && ($request->input('company_id') !== $project->company_id || $request->input('currency') !== $project->currency || $request->input('billing_model') !== $project->billing_model), 403);

        /** @var array<string, mixed> $values */
        $values = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191'],
            'company_id' => ['required', 'ulid', function (string $attribute, mixed $value, callable $fail) use ($project): void {
                // A project keeps its customer even when that one was archived.
                if (! is_string($value) || ($value !== $project?->company_id && ! $this->companies->isActive($value))) {
                    $fail(__('projects:errors.company'));
                }
            }],
            'status' => [$project === null ? 'missing' : 'sometimes', Rule::in(Project::STATUSES)],
            'billing_model' => ['required', Rule::in(Project::BILLING_MODELS)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'budget_minutes' => ['nullable', 'integer', 'between:0,60000000'],
            'budget_minor' => ['sometimes', 'nullable', 'integer', 'between:0,1000000000000'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}\z/'],
            'amber_percent' => ['sometimes', 'integer', 'between:1,100'],
        ])->validate();

        return $values;
    }
}

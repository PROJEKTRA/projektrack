<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Http;

use App\Http\Controllers\Controller;
use App\Models\ProjectRole;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Projects\Domain\Contract;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\Projects;
use Modules\Projects\Domain\WorkPackage;

/**
 * Projects (S-0940): list, details, create and change.
 */
class ProjectController extends Controller
{
    public function index(Request $request, Projects $projects, ProjectView $view, CompanyDirectory $companies, TenantContext $tenant): Response
    {
        $user = $this->currentUser($request);
        $status = $request->query('status');
        $status = in_array($status, Project::STATUSES, true) ? $status : 'active';
        $page = $projects->visibleTo($user)->where('status', $status)->orderBy('name')->orderBy('id')->paginate(50)->withQueryString();

        return Inertia::render('Projects::Projects', [
            'projects' => ['data' => $view->rows(array_values($page->items())), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
            'status' => $status,
            'form' => $this->form($user, $companies, $tenant, null),
        ]);
    }

    public function show(Request $request, string $project, ProjectView $view, CompanyDirectory $companies, TenantContext $tenant): Response
    {
        $user = $this->currentUser($request);
        $model = Project::query()->findOrFail($project);
        $mayMembers = $user->can('projects.members.manage', $model);

        return Inertia::render('Projects::Project', [
            'project' => $view->details($model, $user),
            'form' => $this->form($user, $companies, $tenant, $model),
            'users' => $mayMembers ? User::query()->orderBy('name')->get()->map(fn (User $u): array => ['value' => $u->id, 'text' => $u->name])->all() : [],
            'roles' => $mayMembers ? ProjectRole::query()->orderBy('id')->get()->map(fn (ProjectRole $r): array => ['value' => $r->id, 'text' => $view->roleName($r)])->all() : [],
            'contractTypes' => Contract::TYPES,
            'categories' => WorkPackage::CATEGORIES,
        ]);
    }

    public function store(Request $request, ProjectInput $input, Projects $projects): RedirectResponse
    {
        $user = $this->currentUser($request);
        $lead = ProjectRole::query()->where('key', 'project_lead')->firstOrFail();
        $project = $projects->create($input->validate($request, $user, null), $user, $lead->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects:saved')]);

        return to_route('projects.show', $project->id);
    }

    public function update(Request $request, string $project, ProjectInput $input, Projects $projects): RedirectResponse
    {
        $user = $this->currentUser($request);
        $model = Project::query()->findOrFail($project);
        $projects->update($model, $input->validate($request, $user, $model), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects:saved')]);

        return to_route('projects.show', $project);
    }

    /** @return array<string, mixed> */
    private function form(User $user, CompanyDirectory $companies, TenantContext $tenant, ?Project $project): array
    {
        $on = $project === null ? [] : [$project];
        $edit = $project === null ? $user->can('projects.create') : $user->can('projects.manage', $on);
        $choices = $edit ? $companies->choices() : [];
        if ($edit && $project !== null && ! in_array($project->company_id, array_column($choices, 'id'), true)) {
            $choices[] = ['id' => $project->company_id, 'number' => '', 'name' => $companies->names([$project->company_id])[$project->company_id] ?? ''];
        }

        return [
            'companies' => array_map(fn (array $c): array => ['value' => $c['id'], 'text' => $c['name']], $choices),
            'billingModels' => Project::BILLING_MODELS,
            'statuses' => Project::STATUSES,
            'currency' => $tenant->current()->base_currency,
            'can' => [
                'edit' => $edit,
                'money' => $user->can('projects.budget.view', $on),
                'members' => $project !== null && $user->can('projects.members.manage', $on),
                'contracts' => $project !== null && $user->can('projects.contracts.manage', $on),
                'wbs' => $project !== null && $user->can('projects.wbs.manage', $on),
            ],
        ];
    }
}

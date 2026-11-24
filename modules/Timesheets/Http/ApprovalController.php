<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Http;

use App\Authorization\Permissions;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\Timesheets;

/**
 * Submitted time the user may decide on (S-0947). Never the own.
 */
class ApprovalController extends Controller
{
    public function index(Request $request, Timesheets $timesheets, ProjectDirectory $projects, Permissions $permissions): Response
    {
        $user = $this->currentUser($request);
        abort_unless(in_array('timesheets.approve', $permissions->anywhere($user), true), 403);
        $entries = $timesheets->pendingFor($user)->orderBy('user_id')->orderBy('work_date')->orderBy('id')->limit(500)->get();
        $names = User::query()->whereIn('id', $entries->pluck('user_id')->unique()->values())->get()->keyBy('id');
        $packages = $projects->workPackages(array_values($entries->map(fn (TimeEntry $e): string => $e->work_package_id)->unique()->all()));

        return Inertia::render('Timesheets::Approvals', [
            'entries' => $entries->map(fn (TimeEntry $e): array => [
                ...$e->only(['id', 'duration_minutes', 'billable_minutes', 'description']),
                'date' => $e->work_date->toDateString(),
                'user' => $names->get($e->user_id)->name ?? '',
                'package' => ($packages[$e->work_package_id]['code'] ?? '').' '.($packages[$e->work_package_id]['name'] ?? ''),
                'project' => $packages[$e->work_package_id]['project'] ?? '',
            ])->all(),
        ]);
    }

    public function decide(Request $request, Timesheets $timesheets, Permissions $permissions): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless(in_array('timesheets.approve', $permissions->anywhere($user), true), 403);
        /** @var array{ids: list<string>, decision: string, reason?: string|null} $data */
        $data = Validator::make($request->all(), [
            'ids' => ['required', 'list', 'min:1', 'max:500'],
            'ids.*' => ['ulid'],
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:500'],
        ])->validate();

        $count = $data['decision'] === 'approve' ? $timesheets->approve($user, $data['ids']) : $timesheets->reject($user, $data['ids'], $data['reason'] ?? '');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('timesheets:decided', ['count' => $count])]);

        return to_route('timesheets.approvals.index');
    }
}

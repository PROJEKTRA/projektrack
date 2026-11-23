<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\TimePeriod;
use Modules\Timesheets\Domain\Timesheets;

/**
 * Month close (S-0948) and corrections of approved time (S-0947).
 */
class PeriodController extends Controller
{
    public function index(Request $request, TenantContext $tenant, ProjectDirectory $projects): Response
    {
        $month = $request->query('month');
        $start = CarbonImmutable::parse(is_string($month) && preg_match('/^20\d{2}-(0[1-9]|1[0-2])\z/', $month) === 1 ? "{$month}-01" : now($tenant->current()->timezone)->startOfMonth()->toDateString(), 'UTC');
        $entries = TimeEntry::query()->whereBetween('work_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->orderBy('work_date')->orderBy('id')->limit(2000)->get();
        $names = User::query()->whereIn('id', $entries->pluck('user_id')->unique()->values())->get()->keyBy('id');
        $packages = $projects->workPackages(array_values($entries->map(fn (TimeEntry $e): string => $e->work_package_id)->unique()->all()));

        return Inertia::render('Timesheets::Periods', [
            'month' => $start->format('Y-m'),
            'previous' => $start->subMonth()->format('Y-m'),
            'next' => $start->addMonth()->format('Y-m'),
            'locked' => TimePeriod::isLocked($start->toDateString()),
            'canCorrect' => $this->currentUser($request)->can('timesheets.correct'),
            'me' => $this->currentUser($request)->id,
            'entries' => $entries->map(fn (TimeEntry $e): array => [
                ...$e->only(['id', 'user_id', 'duration_minutes', 'billable_minutes', 'description', 'status', 'corrects_id', 'correction_reason']),
                'date' => $e->work_date->toDateString(),
                'user' => $names->get($e->user_id)->name ?? '',
                'project' => $packages[$e->work_package_id]['project'] ?? '',
                'package' => ($packages[$e->work_package_id]['code'] ?? '').' '.($packages[$e->work_package_id]['name'] ?? ''),
            ])->all(),
        ]);
    }

    public function update(Request $request, Timesheets $timesheets): RedirectResponse
    {
        $request->validate([
            'month' => ['required', 'string', 'regex:/^20\d{2}-(0[1-9]|1[0-2])\z/'],
            'locked' => ['required', 'boolean'],
            'reason' => ['nullable', 'exclude_if:locked,true,1', 'required', 'string', 'max:500'],
        ]);
        $month = $request->string('month')->toString();
        $locked = $request->boolean('locked');
        $timesheets->setLocked($this->currentUser($request), $month, $locked, $locked ? null : $request->string('reason')->toString());

        return to_route('timesheets.periods.index', ['month' => $month]);
    }

    public function correct(Request $request, string $entry, Timesheets $timesheets): RedirectResponse
    {
        $request->validate(['minutes' => ['required', 'integer', 'between:0,1440'], 'reason' => ['required', 'string', 'max:500']]);
        $original = TimeEntry::query()->findOrFail($entry);
        $timesheets->correct($this->currentUser($request), $original, $request->integer('minutes'), $request->string('reason')->toString());

        return to_route('timesheets.periods.index', ['month' => $original->work_date->format('Y-m')]);
    }
}

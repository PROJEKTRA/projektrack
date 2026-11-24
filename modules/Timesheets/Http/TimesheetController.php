<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Http;

use App\Http\Controllers\Controller;
use App\Settings\Settings;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\TimePeriod;
use Modules\Timesheets\Domain\Timers;
use Modules\Timesheets\Domain\Timesheets;

/**
 * The week of the signed-in user (S-0945).
 */
class TimesheetController extends Controller
{
    public function index(Request $request, ProjectDirectory $projects, TenantContext $tenant, Settings $settings, Timers $timers): Response
    {
        $user = $this->currentUser($request);
        $date = $request->query('date');
        $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}\z/', $date) === 1 && strtotime($date) !== false ? $date : now($user->timezone ?? $tenant->current()->timezone)->toDateString();
        [$from, $to] = Timesheets::week($date);
        $entries = TimeEntry::query()->where('user_id', $user->id)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->orderBy('id')->get();

        // Packages of the week: what can be booked on any of its days, and what was booked.
        $packages = [];
        $days = [];
        for ($day = CarbonImmutable::parse($from, 'UTC'); $day->toDateString() <= $to; $day = $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'locked' => TimePeriod::isLocked($day->toDateString())];
            foreach ($projects->bookable($user->id, $day->toDateString()) as $package) {
                $packages[$package['id']] = $package;
            }
        }
        $booked = $projects->workPackages(array_values($entries->map(fn (TimeEntry $e): string => $e->work_package_id)->unique()->all()));

        return Inertia::render('Timesheets::Week', [
            'from' => $from,
            'days' => $days,
            'packages' => array_values([...$packages, ...$booked]),
            'bookable' => array_keys($packages),
            'entries' => $entries->map(fn (TimeEntry $e): array => [
                ...$e->only(['id', 'work_package_id', 'duration_minutes', 'billable_minutes', 'description', 'status', 'reject_reason', 'correction_reason']),
                'date' => $e->work_date->toDateString(),
                'correction' => $e->corrects_id !== null,
            ])->all(),
            'dayWarning' => $settings->get('timesheets.day_warning_minutes'),
            'timer' => ($timer = $timers->running($user)) === null ? null : ['work_package_id' => $timer->work_package_id, 'started_at' => $timer->started_at->toIso8601ZuluString(), 'name' => $projects->workPackages([$timer->work_package_id])[$timer->work_package_id]['name'] ?? ''],
            'canBook' => $user->can('timesheets.book'),
        ]);
    }

    public function book(Request $request, Timesheets $timesheets): JsonResponse
    {
        $entry = $timesheets->book($this->currentUser($request), ...self::cell($request));

        return response()->json($entry === null ? ['entry' => null] : ['entry' => [
            ...$entry->only(['id', 'work_package_id', 'duration_minutes', 'billable_minutes', 'description', 'status']),
            'date' => $entry->work_date->toDateString(),
        ]]);
    }

    public function submit(Request $request, Timesheets $timesheets): RedirectResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = $request->string('date')->toString();
        $count = $timesheets->submitWeek($this->currentUser($request), $date);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('timesheets:submitted', ['count' => $count])]);

        return to_route('timesheets.index', ['date' => $date]);
    }

    /** @return array{workPackageId: string, date: string, minutes: int, description: string|null} */
    public static function cell(Request $request): array
    {
        $request->validate([
            'work_package_id' => ['required', 'ulid'],
            'date' => ['required', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'minutes' => ['required', 'integer', 'between:0,1440'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $description = $request->input('description');

        return [
            'workPackageId' => $request->string('work_package_id')->lower()->toString(),
            'date' => $request->string('date')->toString(),
            'minutes' => $request->integer('minutes'),
            'description' => is_string($description) ? $description : null,
        ];
    }
}

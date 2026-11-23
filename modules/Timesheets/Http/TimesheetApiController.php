<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Http;

use App\Api\ApiOperation;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\Timesheets;

class TimesheetApiController extends Controller
{
    private const array ENTRY = [
        'type' => 'object',
        'required' => ['id', 'date', 'work_package_id', 'duration_minutes', 'billable_minutes', 'status'],
        'properties' => ['id' => ['type' => 'string'], 'date' => ['type' => 'string', 'format' => 'date'], 'work_package_id' => ['type' => 'string'], 'duration_minutes' => ['type' => 'integer'], 'billable_minutes' => ['type' => 'integer'], 'description' => ['type' => ['string', 'null']], 'status' => ['type' => 'string']],
    ];

    #[ApiOperation(summary: 'Time entries of the token user between two dates ("from", "to", at most 93 days).', scope: 'timesheets:read', permission: 'timesheets.view', response: ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => self::ENTRY]]], tag: 'timesheets')]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $last = CarbonImmutable::parse($request->string('from')->toString(), 'UTC')->addDays(93)->toDateString();
        $request->validate(['to' => ["before_or_equal:{$last}"]]);
        $entries = TimeEntry::query()->where('user_id', $this->currentUser($request)->id)
            ->whereBetween('work_date', [$request->string('from')->toString(), $request->string('to')->toString()])->orderBy('work_date')->orderBy('id')->get();

        return response()->json(['data' => $entries->map($this->row(...))->all()]);
    }

    #[ApiOperation(
        summary: 'Set the time of one work package on one day; 0 minutes empty it. Submitted and approved time stays.',
        scope: 'timesheets:write',
        permission: 'timesheets.book',
        response: ['type' => 'object', 'required' => ['entry'], 'properties' => ['entry' => ['type' => ['object', 'null']]]],
        request: ['type' => 'object', 'required' => ['work_package_id', 'date', 'minutes'], 'properties' => ['work_package_id' => ['type' => 'string'], 'date' => ['type' => 'string', 'format' => 'date'], 'minutes' => ['type' => 'integer'], 'description' => ['type' => ['string', 'null']]]],
        tag: 'timesheets',
    )]
    public function book(Request $request, Timesheets $timesheets): JsonResponse
    {
        $entry = $timesheets->book($this->currentUser($request), ...TimesheetController::cell($request));

        return response()->json(['entry' => $entry === null ? null : $this->row($entry)]);
    }

    /** @return array<string, mixed> */
    private function row(TimeEntry $entry): array
    {
        return [...$entry->only(['id', 'work_package_id', 'duration_minutes', 'billable_minutes', 'description', 'status']), 'date' => $entry->work_date->toDateString()];
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Middleware\GuardApiRequest;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Timesheets\Http\ApprovalController;
use Modules\Timesheets\Http\PeriodController;
use Modules\Timesheets\Http\TimesheetApiController;
use Modules\Timesheets\Http\TimesheetController;

// Time tracking (S-0944 to S-0948).
Route::middleware(['web', 'auth', 'verified', 'module:timesheets'])->prefix('timesheets')->name('timesheets.')->group(function () {
    Route::get('/', [TimesheetController::class, 'index'])->middleware('permission:timesheets.view')->name('index');
    Route::put('cells', [TimesheetController::class, 'book'])->middleware(['permission:timesheets.book', 'throttle:240,1'])->name('book');
    Route::post('submit', [TimesheetController::class, 'submit'])->middleware('permission:timesheets.book')->name('submit');

    // Project leads approve in their projects only: the query holds the right, not the route.
    Route::get('approvals', [ApprovalController::class, 'index'])->middleware('access:self')->name('approvals.index');
    Route::post('approvals', [ApprovalController::class, 'decide'])->middleware('access:self')->name('approvals.decide');

    Route::post('entries/{entry}/corrections', [PeriodController::class, 'correct'])->whereUlid('entry')->middleware('permission:timesheets.correct')->name('correct');
    Route::get('periods', [PeriodController::class, 'index'])->middleware('permission:timesheets.lock_period')->name('periods.index');
    Route::post('periods', [PeriodController::class, 'update'])->middleware('permission:timesheets.lock_period')->name('periods.update');
});

Route::prefix('api/v1/timesheets')->name('api.v1.timesheets.')
    ->middleware(['api', 'throttle:api-ip', 'auth:sanctum', 'access:self', GuardApiRequest::class, SetLocale::class, 'throttle:api', 'module:timesheets'])
    ->controller(TimesheetApiController::class)
    ->group(function () {
        Route::get('entries', 'index')->middleware(['scope:timesheets:read', 'permission:timesheets.view'])->name('index');
        Route::put('entries', 'book')->middleware(['scope:timesheets:write', 'permission:timesheets.book'])->name('book');
    });

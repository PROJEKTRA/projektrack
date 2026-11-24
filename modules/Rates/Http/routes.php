<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Support\Facades\Route;
use Modules\Rates\Http\RateController;

// Contribution margin (S-0950): costs only with their own permission.
Route::middleware(['web', 'auth', 'verified', 'module:rates', 'permission:rates.view_cost'])->get('rates/margin', [RateController::class, 'margin'])->name('rates.margin');

// Hourly rates (S-0950).
Route::middleware(['web', 'auth', 'verified', 'module:rates', 'permission:rates.manage'])->prefix('rates')->name('rates.')->controller(RateController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:60,1')->name('store');
    Route::post('job-roles', 'jobRole')->name('job_role');
    Route::post('job-roles/assign', 'assign')->name('assign');
    Route::post('exchange', 'exchange')->middleware('throttle:60,1')->name('exchange');
    Route::post('{rule}/end', 'end')->whereUlid('rule')->name('end');
});

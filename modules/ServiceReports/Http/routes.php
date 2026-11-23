<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Support\Facades\Route;
use Modules\ServiceReports\Http\ReportController;

// Service reports (S-0953). Project leads see and generate those of their projects:
// the query holds the right, not the route.
Route::middleware(['web', 'auth', 'verified', 'module:service_reports', 'access:self'])->prefix('service-reports')->name('service_reports.')->controller(ReportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
    Route::get('{report}/pdf', 'pdf')->whereUlid('report')->middleware('throttle:60,1')->name('pdf');
    Route::get('{report}/csv', 'csv')->whereUlid('report')->name('csv');
});

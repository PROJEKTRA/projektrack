<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Support\Facades\Route;
use Modules\Rates\Http\RateController;

// Hourly rates (S-0950).
Route::middleware(['web', 'auth', 'verified', 'module:rates', 'permission:rates.manage'])->prefix('rates')->name('rates.')->controller(RateController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:60,1')->name('store');
    Route::post('exchange', 'exchange')->middleware('throttle:60,1')->name('exchange');
    Route::post('{rule}/end', 'end')->whereUlid('rule')->name('end');
});

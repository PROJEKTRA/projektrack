<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Http\InvoiceController;

// Invoices (S-0961 to S-0966).
Route::middleware(['web', 'auth', 'verified', 'module:invoices', 'permission:invoices.view'])->prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('permission:invoices.create')->name('store');
    Route::post('from-time', 'fromTime')->middleware('permission:invoices.create')->name('from_time');
    Route::prefix('{invoice}')->whereUlid('invoice')->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'update')->middleware('permission:invoices.create')->name('update');
        Route::delete('/', 'discard')->middleware('permission:invoices.create')->name('discard');
        Route::get('pdf', 'pdf')->middleware('throttle:60,1')->name('pdf');
        Route::post('finalize', 'finalize')->middleware('permission:invoices.finalize')->name('finalize');
        Route::post('cancel', 'cancel')->middleware('permission:invoices.cancel')->name('cancel');
        Route::post('payments', 'pay')->middleware('permission:invoices.payments.record')->name('pay');
        Route::put('fiscal-reference', 'fiscal')->middleware('permission:invoices.finalize')->name('fiscal');
    });
});

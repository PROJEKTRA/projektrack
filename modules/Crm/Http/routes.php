<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Middleware\GuardApiRequest;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\CompanyApiController;
use Modules\Crm\Http\CompanyController;

// Customers (S-0939). Served only where the module is switched on.
Route::middleware(['web', 'auth', 'verified', 'module:crm', 'permission:crm.view'])
    ->prefix('crm/companies')
    ->name('crm.companies.')
    ->controller(CompanyController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{company}', 'show')->whereUlid('company')->name('show');
        Route::post('/', 'store')->middleware(['permission:crm.manage', 'throttle:60,1'])->name('store');
        Route::put('{company}', 'update')->whereUlid('company')->middleware(['permission:crm.manage', 'throttle:60,1'])->name('update');
    });

Route::prefix('api/v1/crm/companies')
    ->name('api.v1.crm.companies.')
    ->middleware(['api', 'throttle:api-ip', 'auth:sanctum', 'access:self', GuardApiRequest::class, SetLocale::class, 'throttle:api', 'module:crm', 'permission:crm.view'])
    ->controller(CompanyApiController::class)
    ->group(function () {
        Route::get('/', 'index')->middleware('scope:crm:read')->name('index');
        Route::get('{company}', 'show')->whereUlid('company')->middleware('scope:crm:read')->name('show');
        Route::post('/', 'store')->middleware(['scope:crm:write', 'permission:crm.manage'])->name('store');
        Route::put('{company}', 'update')->whereUlid('company')->middleware(['scope:crm:write', 'permission:crm.manage'])->name('update');
    });

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Middleware\GuardApiRequest;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\ContractController;
use Modules\Projects\Http\MemberController;
use Modules\Projects\Http\ProjectApiController;
use Modules\Projects\Http\ProjectController;
use Modules\Projects\Http\WorkPackageController;

// Projects (S-0940 to S-0942). The list holds only projects the user may open.
Route::middleware(['web', 'auth', 'verified', 'module:projects'])->prefix('projects')->name('projects.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->middleware('access:self')->name('index');
    Route::post('/', [ProjectController::class, 'store'])->middleware(['permission:projects.create', 'throttle:60,1'])->name('store');

    Route::prefix('{project}')->whereUlid('project')->group(function () {
        Route::get('/', [ProjectController::class, 'show'])->middleware('permission:projects.view,project')->name('show');
        Route::put('/', [ProjectController::class, 'update'])->middleware('permission:projects.manage,project')->name('update');
        Route::post('members', [MemberController::class, 'store'])->middleware('permission:projects.members.manage,project')->name('members.store');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->whereUlid('member')->middleware('permission:projects.members.manage,project')->name('members.destroy');
        Route::post('contracts', [ContractController::class, 'store'])->middleware('permission:projects.contracts.manage,project')->name('contracts.store');
        Route::put('contracts/{contract}', [ContractController::class, 'update'])->whereUlid('contract')->middleware('permission:projects.contracts.manage,project')->name('contracts.update');
        Route::post('work-packages', [WorkPackageController::class, 'store'])->middleware('permission:projects.wbs.manage,project')->name('wbs.store');
        Route::put('work-packages/{package}', [WorkPackageController::class, 'update'])->whereUlid('package')->middleware('permission:projects.wbs.manage,project')->name('wbs.update');
        Route::post('work-packages/{package}/move', [WorkPackageController::class, 'move'])->whereUlid('package')->middleware('permission:projects.wbs.manage,project')->name('wbs.move');
    });
});

Route::prefix('api/v1/projects')->name('api.v1.projects.')
    ->middleware(['api', 'throttle:api-ip', 'auth:sanctum', 'access:self', GuardApiRequest::class, SetLocale::class, 'throttle:api', 'module:projects', 'scope:projects:read'])
    ->controller(ProjectApiController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{project}', 'show')->whereUlid('project')->name('show');
    });

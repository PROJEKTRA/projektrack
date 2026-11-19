<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Middleware\GuardApiRequest;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
 * REST API v1 (S-0913). Personal access tokens only; every route needs a
 * token scope ("scope:") and the same permissions as the UI ("permission:"),
 * inside the tenant of the host. Each route is described by #[ApiOperation]
 * and documented in docs/api/openapi.json (php artisan projektrack:api:openapi).
 */
Route::prefix('v1')
    ->name('api.v1.')
    // Limit by IP before the token is looked up, then per token and user (S-0913).
    ->middleware(['throttle:api-ip', 'auth:sanctum', 'access:self', GuardApiRequest::class, SetLocale::class, 'throttle:api'])
    ->group(function () {
        Route::get('me', [MeController::class, 'show'])->middleware('scope:core:read')->name('me');

        Route::get('notifications', [NotificationController::class, 'index'])->middleware('scope:core:read')->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'readAll'])->middleware('scope:core:write')->name('notifications.read-all');

        Route::get('settings/{component}', [SettingsController::class, 'show'])
            ->middleware(['scope:core:admin', 'permission:core.settings.manage'])
            ->where('component', '[a-z][a-z0-9_]{1,40}')
            ->name('settings.show');

        Route::post('files', [FileController::class, 'store'])
            ->middleware(['scope:core:write', 'permission:core.files.upload'])
            ->name('files.store');
        Route::get('files/{file}', [FileController::class, 'show'])
            ->middleware('scope:core:read')
            ->whereUlid('file')
            ->name('files.show');
    });

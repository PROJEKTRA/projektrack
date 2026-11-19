<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\NotificationSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SessionController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

// Own account only: every route here acts on the signed-in user.
Route::middleware(['auth', 'access:self'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', 'access:self'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    // Sign out every other session (S-0903).
    Route::delete('settings/sessions', [SessionController::class, 'destroy'])
        ->middleware(RequirePassword::class)
        ->name('sessions.destroy');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    // Personal access tokens for the REST API (S-0913).
    Route::middleware('permission:core.api_tokens.manage')->group(function () {
        // no-store: the page may show a new token's secret once.
        Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])->middleware('cache.headers:no_store;private')->name('api-tokens.index');
        Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])
            ->middleware([RequirePassword::class, 'throttle:10,1'])
            ->name('api-tokens.store');
        Route::delete('settings/api-tokens/{token}', [ApiTokenController::class, 'destroy'])
            ->whereUlid('token')
            ->name('api-tokens.destroy');
    });

    // E-mail per notification type (S-0912).
    Route::get('settings/notifications', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
    Route::put('settings/notifications', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');

    Route::get('settings/appearance', fn (): Response => Inertia::render('settings/Appearance'))->name('appearance.edit');
});

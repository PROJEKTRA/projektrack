<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Controllers\Admin\UserTwoFactorController;
use App\Http\Controllers\StringBundleController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/*
 * Deny by default: every route carries "permission:<key>" (or a "can:" policy
 * check) or an explicit "access:public" / "access:self" marker. The route test
 * in tests/Feature/Security/RouteAuthorizationTest.php enforces this.
 */

// Resolved strings per language chain (S-0916). Shipped strings are not secret and
// sign-in pages need them, so the bundle is public and runs without a session.
Route::get('strings/{chain}/{version}.json', StringBundleController::class)
    ->where(['chain' => '[A-Za-z.-]+', 'version' => '[a-f0-9]{16}'])
    ->withoutMiddleware('web')
    ->middleware('access:public')
    ->name('strings.bundle');

// No public landing page: the root sends visitors straight to sign-in or the dashboard.
Route::get('/', fn (): RedirectResponse => to_route('dashboard'))->middleware('access:public')->name('home');

Route::middleware(['auth', 'verified', 'access:self'])->group(function () {
    Route::get('dashboard', fn (): Response => Inertia::render('Dashboard'))->name('dashboard');
});

// User administration. Sensitive steps need a recent password confirmation (15 minutes).
Route::middleware(['auth', 'verified', 'permission:core.users.manage'])->group(function () {
    Route::delete('users/{user}/two-factor', [UserTwoFactorController::class, 'destroy'])
        ->middleware(RequirePassword::class)
        ->name('users.two-factor.reset');
});

require __DIR__.'/settings.php';

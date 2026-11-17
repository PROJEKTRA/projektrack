<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/*
 * Deny by default: every route carries "permission:<key>" (or a "can:" policy
 * check) or an explicit "access:public" / "access:self" marker. The route test
 * in tests/Feature/Security/RouteAuthorizationTest.php enforces this.
 */

// No public landing page: the root sends visitors straight to sign-in or the dashboard.
Route::get('/', fn (): RedirectResponse => to_route('dashboard'))->middleware('access:public')->name('home');

Route::middleware(['auth', 'verified', 'access:self'])->group(function () {
    Route::get('dashboard', fn (): Response => Inertia::render('Dashboard'))->name('dashboard');
});

require __DIR__.'/settings.php';

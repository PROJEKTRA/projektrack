<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

// No public landing page: the root sends visitors straight to sign-in or the dashboard.
Route::get('/', fn (): RedirectResponse => to_route('dashboard'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn (): Response => Inertia::render('Dashboard'))->name('dashboard');
});

require __DIR__.'/settings.php';

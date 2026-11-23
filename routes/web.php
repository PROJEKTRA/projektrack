<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StringController;
use App\Http\Controllers\Admin\UserTwoFactorController;
use App\Http\Controllers\BrandLogoController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SharedLinkController;
use App\Http\Controllers\StringBundleController;
use App\SharedLinks\SharedLinks;
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

// Languages of the tenant (S-0921): offer or withdraw, copy into an own language, export and import.
Route::middleware(['auth', 'verified', 'permission:core.strings.customize'])
    ->prefix('admin/languages')
    ->name('admin.languages.')
    ->controller(LanguageController::class)
    ->where(['locale' => '[A-Za-z]{2,3}(-[A-Za-z0-9]{2,4})?'])
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{locale}', 'update')->name('update');
        Route::get('{locale}/export', 'export')->name('export');
        Route::post('{locale}/import', 'import')->middleware('throttle:10,1')->name('import');
    });

// Own texts for any string of a language (S-0918).
Route::middleware(['auth', 'verified', 'permission:core.strings.customize'])
    ->prefix('admin/languages/{locale}/strings')
    ->name('admin.strings.')
    ->controller(StringController::class)
    ->where(['locale' => '[A-Za-z]{2,3}(-[A-Za-z0-9]{2,4})?'])
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
    });

// Settings declared by the core and enabled modules (S-0912).
Route::middleware(['auth', 'verified', 'permission:core.settings.manage'])
    ->prefix('admin/settings')
    ->name('admin.settings.')
    ->controller(SettingsController::class)
    ->where(['component' => '[a-z][a-z0-9_]{1,40}'])
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('{component}', 'update')->name('update');
    });

// Admin > System > Doctor (S-0988): last report of projektrack doctor, check again.
Route::middleware(['auth', 'verified', 'permission:core.admin'])->group(function () {
    Route::get('admin/doctor', [DoctorController::class, 'index'])->name('admin.doctor.index');
    Route::post('admin/doctor', [DoctorController::class, 'store'])->middleware('throttle:6,1')->name('admin.doctor.store');
});

// Admin > License (S-0976): the license of the installation, behind a fresh password.
Route::middleware(['auth', 'verified', 'permission:core.license.manage', RequirePassword::class])->group(function () {
    Route::get('admin/license', [LicenseController::class, 'show'])->name('admin.license.show');
    Route::post('admin/license', [LicenseController::class, 'store'])->middleware('throttle:6,1')->name('admin.license.store');
});

// Brand (S-0911): the tenant's brand profile, its logo (also before sign-in) and About.
Route::middleware(['auth', 'verified', 'permission:core.branding.manage'])->group(function () {
    Route::get('admin/brand', [BrandController::class, 'edit'])->name('admin.brand.edit');
    Route::post('admin/brand', [BrandController::class, 'update'])->middleware('throttle:20,1')->name('admin.brand.update');
});
Route::get('brand/logo/{version}', BrandLogoController::class)
    ->middleware(['access:public', 'throttle:120,1'])
    ->whereUlid('version')
    ->name('brand.logo');
Route::get('about', AboutController::class)->middleware(['auth', 'verified', 'access:self'])->name('about');

// Uploads and signed downloads (S-0909). A download link is signed, expires and
// is bound to the user it was made for (FileController::download).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('files', [FileController::class, 'store'])
        ->middleware(['permission:core.files.upload', 'throttle:30,1'])
        ->name('files.store');
    Route::get('files/{file}', [FileController::class, 'download'])
        ->middleware(['access:self', 'signed'])
        ->whereUlid('file')
        ->name('files.download');
});

// Shared links (S-0987): one object for people outside the instance. Public,
// limited per address and per link; the token is the only key.
Route::middleware(['access:public', 'throttle:shared-links'])
    ->prefix('s/{token}')
    ->where(['token' => SharedLinks::PATTERN])
    ->name('shared.')
    ->controller(SharedLinkController::class)
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'unlock')->middleware('throttle:shared-links-unlock')->name('unlock');
        Route::match(['get', 'post'], '{action}', 'action')->where('action', '[a-z_]{1,40}')->name('action');
    });

// The signed-in user's notifications (S-0912).
Route::middleware(['auth', 'verified', 'access:self'])
    ->prefix('notifications')
    ->name('notifications.')
    ->controller(NotificationController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('read', 'readAll')->name('read-all');
        Route::post('{notification}/open', 'open')->whereUlid('notification')->name('open');
    });

require __DIR__.'/settings.php';

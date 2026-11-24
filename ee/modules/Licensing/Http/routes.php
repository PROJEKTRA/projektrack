<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Modules\Licensing\Http\OrderController;

// Plans and orders (S-0973, S-0975), behind a fresh password.
Route::middleware(['web', 'auth', 'verified', 'module:licensing', 'permission:licensing.view', RequirePassword::class])->prefix('licensing')->name('licensing.')->controller(OrderController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('plans', 'plan')->middleware('permission:licensing.plans.manage')->name('plan');
    Route::post('orders', 'store')->middleware('permission:licensing.subscriptions.manage')->name('store');
    Route::get('orders/{order}', 'show')->whereUlid('order')->name('show');
    Route::get('orders/{order}/payload', 'payload')->whereUlid('order')->middleware('permission:licensing.subscriptions.manage')->name('payload');
    Route::post('plans/{plan}/retire', 'retire')->whereUlid('plan')->middleware('permission:licensing.plans.manage')->name('retire');
    Route::post('orders/{order}/cancel', 'cancel')->whereUlid('order')->middleware('permission:licensing.subscriptions.manage')->name('cancel');
    Route::post('orders/{order}/invoice', 'invoiceAgain')->whereUlid('order')->middleware('permission:licensing.subscriptions.manage')->name('invoice');
    Route::post('orders/{order}/license', 'enter')->whereUlid('order')->middleware('permission:licensing.subscriptions.manage')->name('enter');
});

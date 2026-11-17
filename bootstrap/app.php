<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Audit\AuditLog;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireModule;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\RouteAccess;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSecurityHeaders;
use App\Tenancy\CrossTenantWrite;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global, in this order: headers wrap every response (also the unknown-host 404),
        // then the tenant is resolved from the host before sessions or auth run.
        $middleware->prepend([SetSecurityHeaders::class, ResolveTenant::class]);

        $middleware->alias([
            'module' => RequireModule::class,
            'permission' => RequirePermission::class,
            'access' => RouteAccess::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureTwoFactorEnrolled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A refused cross-tenant write is a security event (S-0902, S-0907). It is
        // recorded after the failed transaction rolled back, so the entry stays.
        $exceptions->report(function (CrossTenantWrite $e): void {
            if (app(TenantContext::class)->has()) {
                app(AuditLog::class)->record('security.cross_tenant_write', null, ['refused' => $e->getMessage()]);
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Audit\AuditLog;
use App\Http\Middleware\EnsureSessionIsValid;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireModule;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireTokenScope;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\RouteAccess;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\UniformResponseTime;
use App\Tenancy\CrossTenantWrite;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            'scope' => RequireTokenScope::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            EnsureSessionIsValid::class,
            UniformResponseTime::class,
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

        // API errors never name models or internals (S-0913, invisible by default).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('core:api.not_found')], 404);
            }

            return null;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

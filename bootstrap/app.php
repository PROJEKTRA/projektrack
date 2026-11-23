<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Audit\AuditLog;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureSessionIsValid;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RefuseOptionsRequests;
use App\Http\Middleware\RequireModule;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireTokenScope;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\RouteAccess;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\StartSession;
use App\Http\Middleware\UniformResponseTime;
use App\Tenancy\CrossTenantWrite;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Health check without session or cookies: plain "OK" only (S-0986, INV-10).
        then: function (): void {
            Route::get('health', HealthController::class)->middleware('access:public')->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global, in this order: headers wrap every response (also the unknown-host 404),
        // then the tenant is resolved from the host before sessions or auth run.
        $middleware->prepend([SetSecurityHeaders::class, RefuseOptionsRequests::class, ResolveTenant::class]);

        $middleware->alias([
            'module' => RequireModule::class,
            'permission' => RequirePermission::class,
            'access' => RouteAccess::class,
            'scope' => RequireTokenScope::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Shared link addresses never land in the session (S-0987).
        $middleware->web(replace: [Illuminate\Session\Middleware\StartSession::class => StartSession::class]);
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

        // A wrong method says as much as a wrong path: nothing (S-0986, INV-08).
        $exceptions->map(MethodNotAllowedHttpException::class, fn (MethodNotAllowedHttpException $e): NotFoundHttpException => new NotFoundHttpException('', $e));

        // Framework exceptions with telling messages become plain HTTP errors.
        $exceptions->map(ModelNotFoundException::class, fn (ModelNotFoundException $e): NotFoundHttpException => new NotFoundHttpException('', $e));
        $exceptions->map(AuthorizationException::class, fn (AuthorizationException $e): AccessDeniedHttpException => new AccessDeniedHttpException('', $e));
        $exceptions->map(TokenMismatchException::class, fn (TokenMismatchException $e): HttpException => new HttpException(419, '', $e));

        // JSON errors name no route, model or internal, on every path (S-0986, INV-08):
        // one plain sentence per status, in the visitor's language.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $key = in_array($status, [403, 404, 419, 429, 503], true) ? (string) $status : ($status >= 500 ? '5xx' : '4xx');

            return response()->json(['message' => __('core:errors.'.$key.'.title')], $status, $e->getHeaders());
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => __('core:errors.401.title')], 401);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

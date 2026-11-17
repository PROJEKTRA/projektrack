<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/*
 * Deny by default (security baseline, V8): every route carries a permission
 * check ("permission:", "can:") or an explicit "access:public" / "access:self"
 * marker. Routes registered by framework packages cannot carry our markers;
 * they are reviewed here one by one, with the reason they are safe.
 */

const REVIEWED_PACKAGE_ROUTES = [
    // Health check: answers "up", reveals nothing, needs no tenant.
    'GET up' => 'public',
    // Sign-in, password reset and e-mail verification (Fortify): guest or own account.
    'GET login' => 'public',
    'POST login' => 'public',
    'POST logout' => 'self',
    'GET forgot-password' => 'public',
    'POST forgot-password' => 'public',
    'GET reset-password/{token}' => 'public',
    'POST reset-password' => 'public',
    'GET two-factor-challenge' => 'public',
    'POST two-factor-challenge' => 'public',
    'GET email/verify' => 'self',
    'GET email/verify/{id}/{hash}' => 'self',
    'POST email/verification-notification' => 'self',
    'GET user/confirm-password' => 'self',
    'POST user/confirm-password' => 'self',
    'GET user/confirmed-password-status' => 'self',
    // Two-factor management of the signed-in user (Fortify, behind password confirmation).
    'POST user/two-factor-authentication' => 'self',
    'DELETE user/two-factor-authentication' => 'self',
    'POST user/confirmed-two-factor-authentication' => 'self',
    'GET user/two-factor-qr-code' => 'self',
    'GET user/two-factor-secret-key' => 'self',
    'GET user/two-factor-recovery-codes' => 'self',
    'POST user/two-factor-recovery-codes' => 'self',
];

/** Development tooling that registers routes only in the local environment. */
const LOCAL_ONLY_PREFIXES = ['_inertia/devtools/'];

function routeId(RouteDefinition $route): string
{
    $method = collect($route->methods())->reject(fn (string $m): bool => $m === 'HEAD')->first();

    return "{$method} {$route->uri()}";
}

function isAuthorized(RouteDefinition $route): bool
{
    return collect($route->gatherMiddleware())->contains(
        fn (mixed $middleware): bool => is_string($middleware)
            && (str_starts_with($middleware, 'permission:') || str_starts_with($middleware, 'can:') || str_starts_with($middleware, 'access:')),
    );
}

it('covers every route with a permission check or an explicit access marker', function () {
    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RouteDefinition $route): bool => isAuthorized($route))
        ->reject(fn (RouteDefinition $route): bool => array_key_exists(routeId($route), REVIEWED_PACKAGE_ROUTES))
        ->reject(fn (RouteDefinition $route): bool => collect(LOCAL_ONLY_PREFIXES)->contains(fn (string $p): bool => str_starts_with($route->uri(), $p)))
        ->map(fn (RouteDefinition $route): string => routeId($route))
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

it('keeps the reviewed package routes list current', function () {
    $existing = collect(Route::getRoutes()->getRoutes())->map(fn (RouteDefinition $route): string => routeId($route))->all();

    expect(array_diff(array_keys(REVIEWED_PACKAGE_ROUTES), $existing))->toBe([]);
});

it('protects every reviewed "self" route with authentication', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => (REVIEWED_PACKAGE_ROUTES[routeId($route)] ?? null) === 'self');

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();
        $signedIn = collect($middleware)->contains(fn (mixed $m): bool => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:')));

        expect($signedIn)->toBeTrue(routeId($route).' must require a signed-in user');
    }
});

it('does not serve private files through a generic route', function () {
    expect(collect(Route::getRoutes()->getRoutes())->contains(fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'storage/')))
        ->toBeFalse();
});

it('refuses guests on own-account routes even without the auth middleware', function () {
    Route::middleware(['web', 'access:self'])->get('/_test/self', fn () => 'mine');

    $this->get('/_test/self')->assertForbidden();
});

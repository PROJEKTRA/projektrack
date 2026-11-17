<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Explicit access marker for routes that need no permission (security
 * baseline, deny by default). The route test fails on any route that has
 * neither a permission check nor one of these markers.
 *
 * - "access:public": reachable without signing in (sign-in, health check).
 * - "access:self": acts only on the signed-in user's own account (profile,
 *   password, two-factor, dashboard). Refuses guests as a second line.
 */
class RouteAccess
{
    public function handle(Request $request, Closure $next, string $kind): Response
    {
        match ($kind) {
            'public' => null,
            'self' => abort_unless($request->user() instanceof User, 403),
            default => throw new InvalidArgumentException("Unknown route access marker [{$kind}]."),
        };

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

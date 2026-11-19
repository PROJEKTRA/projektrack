<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An API route needs a token with this scope (S-0913): `scope:core:read`.
 * The scope narrows the token; the user's permissions apply on top.
 */
final class RequireTokenScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $user = $request->user();

        // Only a token request: a session user has no token and no scopes.
        abort_unless($user instanceof User && $user->currentAccessToken() instanceof ApiToken && $user->tokenCan($scope), 403);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

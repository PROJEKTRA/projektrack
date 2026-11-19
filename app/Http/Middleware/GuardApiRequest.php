<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\Authorization\Permissions;
use App\Authorization\TwoFactorRequirement;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rules for every /api/v1 request (S-0913), the API's counterpart of the
 * checks web requests run:
 * - deny by default: a route without a declared token scope is refused;
 * - the token user must still be allowed to use tokens;
 * - a user whose roles require two-factor authentication must have it set up.
 */
final class GuardApiRequest
{
    public function __construct(
        private readonly Permissions $permissions,
        private readonly TwoFactorRequirement $twoFactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $user = $request->user();

        abort_unless($route instanceof Route && self::declaresScope($route), 403);
        abort_unless($user instanceof User && $this->permissions->allows($user, 'core.api_tokens.manage'), 403);
        abort_if($this->twoFactor->isRequired($user) && ! $user->hasEnabledTwoFactorAuthentication(), 403);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    public static function declaresScope(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'scope:')) {
                return true;
            }
        }

        return false;
    }
}

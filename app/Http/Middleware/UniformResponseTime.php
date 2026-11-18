<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Timebox;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers requests that could reveal whether an account exists after the same
 * minimum time (S-0903, security baseline: account enumeration). Sign-in has
 * its own guard (App\Auth\AuthenticateUser); this covers the reset link form.
 */
final class UniformResponseTime
{
    /** Route names answered after a fixed minimum time. */
    public const array ROUTES = ['password.email'];

    public const int MICROSECONDS = 400_000;

    public function handle(Request $request, Closure $next): Response
    {
        $pass = function () use ($request, $next): Response {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        };

        if (! $request->isMethod('POST') || ! $request->routeIs(...self::ROUTES)) {
            return $pass();
        }

        /** @var Response $response */
        $response = (new Timebox)->call(fn (): Response => $pass(), self::MICROSECONDS);

        return $response;
    }
}

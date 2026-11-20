<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OPTIONS requests get the same 404 as an unknown path (S-0986, INV-08):
 * the router would otherwise list the methods of every existing route in
 * an Allow header. There is no CORS to answer (config/cors.php).
 */
final class RefuseOptionsRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->isMethod('OPTIONS'), 404);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\Modules\TenantModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "module:<key>". A disabled module answers like a route that
 * does not exist, so the response does not reveal which modules are installed.
 */
class RequireModule
{
    public function __construct(private readonly TenantModules $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($this->modules->isEnabled($module), 404);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

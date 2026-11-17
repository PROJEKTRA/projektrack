<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant context from the request host before sessions, auth or any
 * route run. Unknown or inactive hosts get a plain, unbranded 404 that says
 * nothing about the product or which tenants exist.
 */
class ResolveTenant
{
    /** Paths that work without a tenant (load balancer health check). */
    private const array TENANTLESS_PATHS = ['up'];

    public function __construct(private readonly TenantResolver $resolver, private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->path(), self::TENANTLESS_PATHS, true)) {
            $tenant = $this->resolver->forHost($request->getHost());

            if ($tenant === null) {
                return response('Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            $this->context->set($tenant);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

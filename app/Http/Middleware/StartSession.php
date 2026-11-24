<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * The framework's session middleware, except that the address of a shared
 * link (S-0987) is never kept as the "previous URL": it holds the token, and
 * sessions are stored unencrypted. A downloaded file is no page to return to
 * either: an error on the page it was downloaded from has to be shown there.
 */
class StartSession extends BaseStartSession
{
    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        return parent::handleStatefulRequest($request, $session, function (Request $request) use ($next): mixed {
            $response = $next($request);
            $request->attributes->set('download', $response instanceof Response && $response->headers->has('Content-Disposition'));

            return $response;
        });
    }

    protected function storeCurrentUrl(Request $request, $session)
    {
        if ($request->routeIs('shared.*') || $request->attributes->get('download') === true) {
            return;
        }

        parent::storeCurrentUrl($request, $session);
    }
}

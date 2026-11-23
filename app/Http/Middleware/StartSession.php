<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;

/**
 * The framework's session middleware, except that the address of a shared
 * link (S-0987) is never kept as the "previous URL": it holds the token, and
 * sessions are stored unencrypted.
 */
class StartSession extends BaseStartSession
{
    protected function storeCurrentUrl(Request $request, $session)
    {
        if ($request->routeIs('shared.*')) {
            return;
        }

        parent::storeCurrentUrl($request, $session);
    }
}

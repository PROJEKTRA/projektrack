<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests as Base;

/**
 * Limits like "throttle:30,1" count per route. Without this all of them share
 * one counter per person, and booking time would block other pages.
 */
final class ThrottleRequests extends Base
{
    /** @param Request $request */
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        return sha1(implode('|', $route?->methods() ?? []).' '.($route?->uri() ?? '')).parent::resolveRequestSignature($request);
    }
}

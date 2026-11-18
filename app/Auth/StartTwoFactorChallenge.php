<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use App\Auth\TwoFactor\TwoFactorLoginRequest;
use Closure;
use Illuminate\Http\Request;

/**
 * Sign-in pipeline step before the two-factor redirect (S-0903): notes when
 * the challenge starts, so it expires (TwoFactorLoginRequest).
 */
final class StartTwoFactorChallenge
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->session()->put(TwoFactorLoginRequest::STARTED, now()->getTimestamp());

        return $next($request);
    }
}

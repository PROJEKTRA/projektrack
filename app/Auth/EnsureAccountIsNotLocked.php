<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * First step of the sign-in pipeline (S-0903): a locked account name is
 * refused before its password is checked, with the same message whether the
 * account exists or not.
 */
final class EnsureAccountIsNotLocked
{
    public function __construct(private readonly LoginThrottle $throttle) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $email = $request->input(Fortify::username());
        $seconds = is_string($email) ? $this->throttle->availableIn($email) : 0;

        if ($seconds > 0) {
            throw ValidationException::withMessages([
                Fortify::username() => __('core:auth.locked', ['minutes' => (int) ceil($seconds / 60)]),
            ]);
        }

        return $next($request);
    }
}

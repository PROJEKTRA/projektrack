<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use App\Models\User;
use Closure;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * The signed-in user's password in a form. Wrong ones count towards the
 * account lock, so a stolen session cannot guess it.
 */
final class CurrentPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = request()->user();
        $throttle = app(LoginThrottle::class);

        if ($user instanceof User && is_string($value) && $throttle->availableIn($user->email) === 0) {
            if (Hash::check($value, $user->password)) {
                return;
            }
            if ($throttle->failed($user->email) > 0) {
                event(new Lockout(request()));
            }
        }

        $fail(__('core:auth.password'));
    }
}

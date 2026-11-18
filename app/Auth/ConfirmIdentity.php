<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Re-authentication before sensitive actions (S-0903): the password, or for
 * users with two-factor authentication a current code of their authenticator
 * app (used once). Failures count towards the account lock, so the code
 * cannot be guessed.
 */
final class ConfirmIdentity
{
    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
    ) {}

    public function __invoke(mixed $user, mixed $secret): bool
    {
        if (! $user instanceof User || ! is_string($secret) || $secret === '') {
            return false;
        }

        if ($this->throttle->availableIn($user->email) > 0) {
            return false;
        }

        $confirmed = Hash::check($secret, $user->password) || $this->validCode($user, $secret);

        if (! $confirmed && $this->throttle->failed($user->email) > 0) {
            event(new Lockout(request()));
        }

        return $confirmed;
    }

    /** Whether the user has two-factor authentication and the code is current. */
    public static function acceptsCode(User $user): bool
    {
        return $user->hasEnabledTwoFactorAuthentication();
    }

    private function validCode(User $user, string $secret): bool
    {
        $code = preg_replace('/\s+/', '', $secret) ?? '';

        if (! self::acceptsCode($user) || preg_match('/^\d{6}$/', $code) !== 1 || ! is_string($user->two_factor_secret)) {
            return false;
        }

        $key = decrypt($user->two_factor_secret);

        return is_string($key) && $this->twoFactor->verify($key, $code);
    }
}

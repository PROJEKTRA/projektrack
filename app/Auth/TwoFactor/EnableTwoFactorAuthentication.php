<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

use App\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyEnable;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider as GoogleProvider;

/**
 * Fortify's enable step with Projektrack recovery codes (hashed, ten, shown once).
 */
class EnableTwoFactorAuthentication extends FortifyEnable
{
    public function __construct(
        TwoFactorAuthenticationProvider $provider,
        private readonly RecoveryCodes $codes,
    ) {
        parent::__construct($provider);
    }

    /**
     * @param  User  $user
     * @param  bool  $force
     */
    public function __invoke($user, $force = false): void
    {
        if (! empty($user->two_factor_secret) && $force !== true) {
            return;
        }

        // 160-bit secrets (32 base32 characters), as RFC 4226 recommends.
        $secret = $this->provider instanceof GoogleProvider
            ? $this->provider->generateSecretKey(32)
            : app(TwoFactorAuthenticationProvider::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        ])->save();

        $this->codes->issue($user);

        TwoFactorAuthenticationEnabled::dispatch($user);
    }
}

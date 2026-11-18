<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

use App\Auth\LoginThrottle;
use App\Models\User;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyRequest;

/**
 * The second sign-in step.
 *
 * - Recovery codes are checked against the stored hashes (S-0904); Fortify
 *   then calls User::replaceRecoveryCode(), which removes the used one.
 * - The account lock applies here too (S-0903): a locked account gets no code
 *   checked and its pending challenge is dropped.
 * - A pending challenge expires after CHALLENGE_SECONDS, so a password once
 *   entered cannot be used for guessing codes later.
 */
class TwoFactorLoginRequest extends FortifyRequest
{
    /** Session key holding the time the password was accepted. */
    public const string STARTED = 'login.challenge_started_at';

    public const int CHALLENGE_SECONDS = 300;

    public function hasChallengedUser(): bool
    {
        return ! $this->expired() && parent::hasChallengedUser();
    }

    /**
     * @return User
     */
    public function challengedUser()
    {
        // An expired challenge has no user: Fortify answers as for a missing one.
        $this->expired();

        /** @var User */
        return parent::challengedUser();
    }

    private function expired(): bool
    {
        $started = $this->session()->get(self::STARTED);

        if (! is_int($started) || now()->getTimestamp() - $started > self::CHALLENGE_SECONDS) {
            $this->session()->forget(['login.id', 'login.remember', self::STARTED]);

            return true;
        }

        return false;
    }

    public function hasValidCode(): bool
    {
        return ! $this->isLocked() && parent::hasValidCode();
    }

    public function validRecoveryCode(): ?string
    {
        $code = $this->input('recovery_code');

        if (! is_string($code) || trim($code) === '' || $this->isLocked()) {
            return null;
        }

        $user = $this->challengedUser();
        $match = app(RecoveryCodes::class)->match($user, $code);

        if ($match !== null) {
            $this->session()->forget('login.id');
        }

        return $match;
    }

    private function isLocked(): bool
    {
        $user = $this->challengedUser();

        if (app(LoginThrottle::class)->availableIn($user->email) > 0) {
            $this->session()->forget(['login.id', 'login.remember', self::STARTED]);

            return true;
        }

        return false;
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

use App\Models\User;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyRequest;

/**
 * Checks a recovery code against the stored hashes instead of plain codes.
 * Fortify then calls User::replaceRecoveryCode(), which removes the used one.
 */
class TwoFactorLoginRequest extends FortifyRequest
{
    public function validRecoveryCode(): ?string
    {
        $code = $this->input('recovery_code');

        if (! is_string($code) || trim($code) === '') {
            return null;
        }

        $user = $this->challengedUser();
        $match = $user instanceof User ? app(RecoveryCodes::class)->match($user, $code) : null;

        if ($match !== null) {
            $this->session()->forget('login.id');
        }

        return $match;
    }
}

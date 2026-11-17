<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

use App\Authorization\TwoFactorRequirement;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisable;

/**
 * A user whose roles require two-factor cannot switch it off. Only an admin
 * reset (which forces new enrollment at the next request) removes it.
 */
class DisableTwoFactorAuthentication extends FortifyDisable
{
    public function __construct(private readonly TwoFactorRequirement $requirement) {}

    /**
     * @param  User  $user
     *
     * @throws ValidationException
     */
    public function __invoke($user): void
    {
        // A setup that was never confirmed may always be cleared (Fortify does so
        // when the user abandons the confirmation step).
        if ($user->hasEnabledTwoFactorAuthentication() && $this->requirement->isRequired($user)) {
            throw ValidationException::withMessages([
                'two_factor' => [__('two_factor.cannot_disable')],
            ]);
        }

        parent::__invoke($user);
    }
}

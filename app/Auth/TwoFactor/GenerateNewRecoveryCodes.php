<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

use App\Models\User;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes as FortifyGenerate;

class GenerateNewRecoveryCodes extends FortifyGenerate
{
    public function __construct(private readonly RecoveryCodes $codes) {}

    /**
     * @param  User  $user
     */
    public function __invoke($user): void
    {
        $this->codes->issue($user);
    }
}

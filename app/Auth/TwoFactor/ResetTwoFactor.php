<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

use App\Auth\Sessions;
use App\Models\User;
use App\Notifications\TwoFactorWasReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An admin removes a user's two-factor setup, for example after a lost phone
 * (S-0904). The user is signed out everywhere, must enroll again at the next
 * sign-in if a role requires it, and is told by e-mail.
 */
final class ResetTwoFactor
{
    public function __invoke(User $user, User $admin): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            // Invalidates "remember me" cookies on every device.
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config()->string('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->notify(new TwoFactorWasReset);

        // Whatever the session driver.
        app(Sessions::class)->endAll($user);

        event(new TwoFactorReset($user->tenant_id, $user->id, $admin->id));
    }
}

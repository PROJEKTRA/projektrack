<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use App\Api\ApiTokens;
use App\Auth\TwoFactor\TwoFactorReset;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Account lock and session rules around sign-in events (S-0903).
 */
final class AuthenticationSubscriber
{
    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly Sessions $sessions,
    ) {}

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Failed::class => 'failed',
            TwoFactorAuthenticationFailed::class => 'twoFactorFailed',
            Login::class => 'login',
            PasswordReset::class => 'passwordReset',
            TwoFactorReset::class => 'twoFactorRemoved',
            TwoFactorAuthenticationDisabled::class => 'twoFactorRemoved',
        ];
    }

    public function failed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        if (is_string($email)) {
            $this->count($email);
        }
    }

    public function twoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->count($event->user->email);
    }

    public function login(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->throttle->clear($event->user->email);

        $request = request();

        if ($request->hasSession()) {
            $this->sessions->start($request->session(), $event->user);
        }
    }

    /** A reset password ends every session of the account. */
    public function passwordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->sessions->endAll($event->user);
            // Proof of the mailbox: the owner gets in again at once.
            $this->throttle->clear($event->user->email);
        }
    }

    /** A removed second factor ends the user's API tokens (S-0913). */
    public function twoFactorRemoved(TwoFactorReset|TwoFactorAuthenticationDisabled $event): void
    {
        $user = $event instanceof TwoFactorReset
            ? User::query()->withoutGlobalScopes()->find($event->userId)
            : $event->user;

        if ($user instanceof User) {
            // Resolved here, not in the constructor: subscribers are built at boot,
            // where the audit log (and its key) may not be available yet.
            app(ApiTokens::class)->revokeAll($user, 'two_factor_removed');
        }
    }

    private function count(string $email): void
    {
        if ($this->throttle->failed($email) > 0) {
            event(new Lockout(request()));
        }
    }
}

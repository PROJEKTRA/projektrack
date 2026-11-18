<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

/**
 * Credential check for sign-in (S-0903). An unknown address costs one password
 * hash like a known one, so the response time does not reveal whether the
 * account exists (security baseline: account enumeration).
 */
final class AuthenticateUser
{
    public function __construct(private readonly Repository $cache) {}

    public function __invoke(Request $request): ?User
    {
        $email = $request->input(Fortify::username());
        $password = $request->input('password');

        if (! is_string($email) || ! is_string($password)) {
            return null;
        }

        // Addresses are stored in lower case (fortify.lowercase_usernames).
        $email = config('fortify.lowercase_usernames') === true ? Str::lower(trim($email)) : trim($email);
        $user = User::query()->where('email', $email)->first();

        // Database collations may match accent or invisible-character variants;
        // only the exact address counts, so the lock key and the account agree.
        if ($user !== null && ! hash_equals(Str::lower($user->email), Str::lower($email))) {
            $user = null;
        }
        $valid = Hash::check($password, $user !== null ? $user->password : $this->dummyHash());

        if (! $valid || $user === null) {
            return null;
        }

        // Older parameters or another algorithm: store an Argon2id hash now.
        if (config('hashing.rehash_on_login') === true && Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        return $user;
    }

    /** A hash with the current parameters, of a password nobody knows. */
    private function dummyHash(): string
    {
        $parameters = hash('sha256', (string) json_encode(config('hashing')));
        /** @var string $hash */
        $hash = $this->cache->rememberForever(
            "auth.dummy-hash.{$parameters}",
            fn (): string => Hash::make(Str::random(40)),
        );

        return $hash;
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\RecoveryCode;

/**
 * Two-factor recovery codes (S-0904): ten single-use codes, stored only as
 * hashes and shown to the user once, right after they were created.
 *
 * Each code carries about 119 bits of randomness, so a plain SHA-256 hash is
 * enough (no slow password hash needed) and survives application key rotation.
 */
final class RecoveryCodes
{
    public const int COUNT = 10;

    /** Plain codes wait here (encrypted session) until the page shows them once. */
    private const string SESSION_KEY = 'two_factor.new_recovery_codes';

    public function __construct(private readonly Request $request) {}

    /**
     * Replace the user's codes with ten new ones.
     *
     * @return list<string> the plain codes, never stored
     */
    public function issue(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $codes[] = RecoveryCode::generate();
        }

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode(array_map(self::hash(...), $codes), JSON_THROW_ON_ERROR),
        ])->save();

        if ($this->request->hasSession()) {
            $this->request->session()->put(self::SESSION_KEY, $codes);
        }

        RecoveryCodesGenerated::dispatch($user);

        return $codes;
    }

    /**
     * The codes created in this session, once. Later calls return nothing.
     *
     * @return list<string>
     */
    public function pullNew(): array
    {
        if (! $this->request->hasSession()) {
            return [];
        }

        $codes = $this->request->session()->pull(self::SESSION_KEY, []);

        return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }

    /**
     * The stored hash that matches $code, or null.
     */
    public function match(User $user, string $code): ?string
    {
        $hash = self::hash($code);

        foreach ($user->recoveryCodes() as $stored) {
            if (hash_equals($stored, $hash)) {
                return $stored;
            }
        }

        return null;
    }

    public static function hash(string $code): string
    {
        return hash('sha256', trim($code));
    }
}

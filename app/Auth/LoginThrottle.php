<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

/**
 * Progressive lockout per account (S-0903, security baseline section 2):
 * 5 failed attempts within 15 minutes lock the account name for 15 minutes,
 * every further lock doubles up to 24 hours.
 *
 * Keyed by tenant and submitted e-mail address, not by the user, so an unknown
 * address is locked exactly like a known one and the lock reveals nothing; a
 * lock in one tenant never locks the same address in another. The address
 * itself never reaches the cache (hashed key).
 */
final class LoginThrottle
{
    public const int ATTEMPTS = 5;

    public const int WINDOW = 15 * 60;

    public const int FIRST_LOCK = 15 * 60;

    public const int MAX_LOCK = 24 * 60 * 60;

    public function __construct(
        private readonly Repository $cache,
        private readonly TenantContext $tenant,
    ) {}

    /** Seconds until the next attempt is allowed; 0 when not locked. */
    public function availableIn(string $email): int
    {
        $state = $this->state($email);

        return max(0, $state['until'] - now()->getTimestamp());
    }

    /**
     * Count a failed attempt (wrong password, unknown address, wrong second
     * factor or confirmation). Returns the lock in seconds when this attempt
     * locked the account, else 0.
     */
    public function failed(string $email): int
    {
        $store = $this->cache->getStore();

        // Attempts at the same moment are all counted.
        if (! $store instanceof LockProvider) {
            return $this->count($email);
        }

        $lock = $store->lock($this->key($email).'.lock', 10);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            return $this->count($email);
        }
        try {
            return $this->count($email);
        } finally {
            $lock->release();
        }
    }

    private function count(string $email): int
    {
        $now = now()->getTimestamp();
        $state = $this->state($email);

        if ($now - $state['first'] > self::WINDOW) {
            $state['failures'] = 0;
            $state['first'] = $now;
        }

        $state['failures']++;
        $lock = 0;

        if ($state['failures'] >= self::ATTEMPTS) {
            $lock = min(self::FIRST_LOCK * 2 ** $state['locks'], self::MAX_LOCK);
            $state['locks']++;
            $state['until'] = $now + $lock;
            $state['failures'] = 0;
            $state['first'] = $now;
        }

        // Locks are remembered for a day after the last one, so they keep doubling.
        $this->cache->put($this->key($email), $state, max($state['until'] - $now, 0) + self::MAX_LOCK);

        return $lock;
    }

    /** A successful sign-in clears failures and earlier locks. */
    public function clear(string $email): void
    {
        $this->cache->forget($this->key($email));
    }

    /**
     * @return array{failures: int, first: int, locks: int, until: int}
     */
    private function state(string $email): array
    {
        $state = $this->cache->get($this->key($email));

        if (! is_array($state)) {
            return ['failures' => 0, 'first' => now()->getTimestamp(), 'locks' => 0, 'until' => 0];
        }

        return [
            'failures' => is_int($state['failures'] ?? null) ? $state['failures'] : 0,
            'first' => is_int($state['first'] ?? null) ? $state['first'] : now()->getTimestamp(),
            'locks' => is_int($state['locks'] ?? null) ? $state['locks'] : 0,
            'until' => is_int($state['until'] ?? null) ? $state['until'] : 0,
        ];
    }

    private function key(string $email): string
    {
        $tenant = $this->tenant->has() ? (string) $this->tenant->id() : '-';

        return 'auth.login-throttle.'.hash('sha256', $tenant.'|'.Str::lower(trim($email)));
    }
}

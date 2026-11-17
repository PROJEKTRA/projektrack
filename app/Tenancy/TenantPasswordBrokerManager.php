<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Tenancy;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\DatabaseManager;

/**
 * Uses the tenant-aware token repository for every password broker.
 */
final class TenantPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        $key = config('app.key');
        $key = is_string($key) ? $key : '';

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7));
        }

        $connection = $config['connection'] ?? null;
        $table = $config['table'] ?? null;
        $expire = $config['expire'] ?? null;
        $throttle = $config['throttle'] ?? null;

        return new TenantPasswordTokenRepository(
            app(DatabaseManager::class)->connection(is_string($connection) ? $connection : null),
            app(Hasher::class),
            is_string($table) ? $table : 'password_reset_tokens',
            $key,
            (is_numeric($expire) ? (int) $expire : 60) * 60,
            is_numeric($throttle) ? (int) $throttle : 0,
        );
    }
}

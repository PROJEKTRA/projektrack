<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Public license keys shipped with the release (config/license-keys.php). A
 * key verifies a token only if the token was issued within the key's validity
 * and the key is not revoked; several keys allow a rotation.
 */
final readonly class LicenseKeys
{
    /** @param  array<array-key, mixed>  $keys */
    public function __construct(private array $keys) {}

    /** @return non-empty-string|null the raw public key */
    public function find(string $kid, CarbonImmutable $issuedAt): ?string
    {
        foreach ($this->keys as $key) {
            if (! is_array($key) || ($key['kid'] ?? null) !== $kid || ! in_array($key['status'] ?? null, ['active', 'retired'], true)) {
                continue;
            }

            $public = is_string($key['public_key'] ?? null) ? base64_decode($key['public_key'], true) : false;
            $from = self::time($key['valid_from'] ?? null);
            $until = self::time($key['valid_until'] ?? null);

            // A key without a complete entry verifies nothing.
            if (is_string($public) && $public !== '' && strlen($public) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                && $from !== null && $until !== null && $issuedAt >= $from && $issuedAt < $until) {
                return $public;
            }
        }

        return null;
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, 'UTC') : null;
        } catch (Throwable) {
            return null;
        }
    }
}

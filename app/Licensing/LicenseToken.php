<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use App\Audit\CanonicalJson;
use InvalidArgumentException;
use JsonException;
use SensitiveParameter;

/**
 * Wire format of a license (S-0974, ADR-0003 section 3):
 * PTKL1.<base64url of the canonical JSON>.<base64url of the Ed25519 signature>.
 * The fixed prefix leaves no algorithm to choose. The signature is checked
 * before the content is used for anything but the key id.
 */
final class LicenseToken
{
    public const string PREFIX = 'PTKL1';

    private const int MAX_LENGTH = 16384;

    /**
     * @param  array<array-key, mixed>  $payload
     * @param  non-empty-string  $secretKey  Ed25519 secret key
     */
    public static function encode(array $payload, #[SensitiveParameter] string $secretKey): string
    {
        $input = self::PREFIX.'.'.self::base64url(self::canonical($payload));

        return $input.'.'.self::base64url(sodium_crypto_sign_detached($input, $secretKey));
    }

    /** @throws InvalidLicense */
    public static function decode(string $token, LicenseKeys $keys): License
    {
        $token = trim($token);
        $parts = explode('.', $token);

        if (strlen($token) > self::MAX_LENGTH || count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            throw new InvalidLicense('This is not a license.');
        }

        $json = self::decode64($parts[1]);
        $signature = self::decode64($parts[2]);

        if ($json === null || $signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new InvalidLicense('This is not a license.');
        }

        try {
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidLicense('This is not a license.');
        }

        // Before the signature is checked, only the key id and the time are read.
        $kid = is_array($payload) ? ($payload['kid'] ?? null) : null;
        $issued = is_array($payload) ? ($payload['issued_at'] ?? null) : null;
        $public = is_string($kid) && is_string($issued) ? $keys->find($kid, License::time($issued)) : null;

        if (! is_array($payload) || $public === null) {
            throw new InvalidLicense('The license is signed with a key this release does not trust.', 'untrusted_key');
        }
        if (! sodium_crypto_sign_verify_detached($signature, self::PREFIX.'.'.$parts[1], $public)) {
            throw new InvalidLicense('The signature of the license is not valid.');
        }

        // Re-encoding must give the same bytes: no duplicate keys, floats or spaces.
        try {
            $canonical = self::canonical($payload);
        } catch (InvalidArgumentException|JsonException) {
            $canonical = null;
        }
        if ($canonical !== $json) {
            throw new InvalidLicense('The license is not in canonical form.');
        }

        return License::fromPayload($payload);
    }

    /** @param  array<array-key, mixed>  $payload */
    private static function canonical(array $payload): string
    {
        // RFC 8785 for what a license holds: ASCII keys, integers, strings, lists and objects.
        return json_encode(json_decode(CanonicalJson::encode($payload), false, 16, JSON_THROW_ON_ERROR),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** Strict: one text form per value, so a token cannot be rewritten. */
    private static function decode64(string $text): ?string
    {
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);

        return is_string($bytes) && $bytes !== '' && self::base64url($bytes) === $text ? $bytes : null;
    }
}

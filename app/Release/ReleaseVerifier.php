<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Release;

/**
 * Verifies a release file before it is installed or applied (S-0924, security
 * baseline section 9): the checksum list SHA256SUMS must carry a valid Ed25519
 * signature of one of the trusted release keys (config release.public_keys),
 * and the file's SHA-256 must be the one the list names for it. Anything else,
 * including a missing key, is refused: it fails closed.
 */
final class ReleaseVerifier
{
    /** @param list<string> $publicKeys base64 of 32-byte Ed25519 public keys */
    public function __construct(private readonly array $publicKeys) {}

    /**
     * @return string|null null when the file is genuine, else why it is refused
     */
    public function verify(string $file, string $sums, string $signature): ?string
    {
        $keys = $this->keys();
        if ($keys === []) {
            return 'No trusted release key is configured.';
        }

        foreach ([$file, $sums, $signature] as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                return 'Not readable: '.basename($path);
            }
        }

        $list = (string) file_get_contents($sums);
        $raw = (string) file_get_contents($signature);
        // Raw 64 bytes from the signing step, or the same in base64.
        $signatureBytes = strlen($raw) === SODIUM_CRYPTO_SIGN_BYTES ? $raw : base64_decode(trim($raw), true);

        if (! is_string($signatureBytes) || strlen($signatureBytes) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return 'The signature file is not an Ed25519 signature.';
        }

        $signed = false;
        foreach ($keys as $key) {
            if (sodium_crypto_sign_verify_detached($signatureBytes, $list, $key)) {
                $signed = true;
                break;
            }
        }
        if (! $signed) {
            return 'The checksum list is not signed by a trusted release key.';
        }

        $expected = $this->expectedHash($list, basename($file));
        if ($expected === null) {
            return 'The checksum list does not name '.basename($file).'.';
        }

        $actual = hash_file('sha256', $file);
        if (! is_string($actual) || ! hash_equals($expected, $actual)) {
            return 'The checksum of '.basename($file).' does not match: it was changed after signing.';
        }

        return null;
    }

    /** @return list<non-empty-string> raw public keys */
    private function keys(): array
    {
        $keys = [];
        foreach ($this->publicKeys as $encoded) {
            $key = base64_decode($encoded, true);
            if (is_string($key) && $key !== '' && strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** The hash the list names for exactly this file name, or null. */
    private function expectedHash(string $list, string $name): ?string
    {
        $found = null;
        foreach (preg_split('/\R/', $list) ?: [] as $line) {
            if (preg_match('/^([0-9a-f]{64}) [ *](.+)$/', $line, $match) !== 1 || $match[2] !== $name) {
                continue;
            }
            // A name listed twice is ambiguous: refuse.
            if ($found !== null) {
                return null;
            }
            $found = $match[1];
        }

        return $found;
    }
}

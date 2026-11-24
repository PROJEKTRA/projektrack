<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Backup;

use RuntimeException;

/**
 * An encrypted backup file. Writing needs only the public key, so a stolen
 * server cannot read its own backups; the secret key is kept elsewhere.
 * Layout: magic, sealed stream key, stream header, then encrypted chunks.
 */
final class BackupFile
{
    private const string MAGIC = "PTKBAK1\n";

    private const int CHUNK = 65536;

    /** @return array{public: string, secret: string} base64 */
    public static function generateKeys(): array
    {
        $pair = sodium_crypto_box_keypair();

        return ['public' => base64_encode(sodium_crypto_box_publickey($pair)), 'secret' => base64_encode(sodium_crypto_box_secretkey($pair))];
    }

    public static function encrypt(string $plainFile, string $target, string $publicKey): void
    {
        $public = self::key($publicKey, SODIUM_CRYPTO_BOX_PUBLICKEYBYTES);
        $in = self::open($plainFile, 'rb');
        $out = self::open($target, 'xb');
        @chmod($target, 0600);
        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        /** @var array{string, string} $started */
        $started = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        [$state, $header] = $started;
        $write = function (string $data) use ($out): void {
            if (fwrite($out, $data) !== strlen($data)) {
                throw new RuntimeException('The backup could not be written. Is the disk full?');
            }
        };
        $write(self::MAGIC.sodium_crypto_box_seal($key, $public).$header);
        do {
            $chunk = (string) stream_get_contents($in, self::CHUNK);
            $last = feof($in) || strlen($chunk) < self::CHUNK;
            $write(sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE));
        } while (! $last);
        sodium_memzero($key);
        fclose($in);
        fclose($out);
    }

    public static function decrypt(string $file, string $plainFile, string $secretKey): void
    {
        $secret = self::key($secretKey, SODIUM_CRYPTO_BOX_SECRETKEYBYTES);
        $pair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));
        $in = self::open($file, 'rb');
        $sealed = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES + SODIUM_CRYPTO_BOX_SEALBYTES;
        if (stream_get_contents($in, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new RuntimeException('This is not a backup file.');
        }
        $key = sodium_crypto_box_seal_open((string) stream_get_contents($in, $sealed), $pair);
        if ($key === false) {
            throw new RuntimeException('The key does not fit this backup.');
        }
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull((string) stream_get_contents($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $key);
        $out = self::open($plainFile, 'xb');
        @chmod($plainFile, 0600);
        $tag = null;
        while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
            $chunk = (string) stream_get_contents($in, self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
            $result = $chunk === '' ? false : sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
            if ($result === false) {
                // Also a file that was cut off: its last part is missing.
                throw new RuntimeException('The backup is damaged.');
            }
            /** @var array{string, int} $result */
            [$plain, $tag] = $result;
            if (fwrite($out, $plain) !== strlen($plain)) {
                throw new RuntimeException('The disk is full.');
            }
        }
        fclose($in);
        fclose($out);
    }

    /** @return non-empty-string */
    private static function key(string $base64, int $length): string
    {
        $key = base64_decode(trim($base64), true);
        if ($key === false || $key === '' || strlen($key) !== $length) {
            throw new RuntimeException('The backup key is not valid.');
        }

        return $key;
    }

    /** @return resource */
    private static function open(string $file, string $mode)
    {
        $handle = @fopen($file, $mode);
        if ($handle === false) {
            throw new RuntimeException(basename($file).' cannot be opened.');
        }

        return $handle;
    }
}

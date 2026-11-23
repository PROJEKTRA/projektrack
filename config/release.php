<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * Release signatures (S-0924, security baseline section 9): public Ed25519
 * keys whose signature over SHA256SUMS a release ZIP must carry before it is
 * installed or applied as an update. They ship with each release in
 * resources/release/public-keys.txt, so an update is checked with the keys
 * of the release that is running. RELEASE_PUBLIC_KEYS (comma-separated)
 * replaces them only when APP_ENV is "testing": on an instance no
 * environment variable or .htaccess line can change what is trusted. The
 * release signing key is separate from the license key and never exists on
 * an instance.
 */

$shipped = dirname(__DIR__).'/resources/release/public-keys.txt';
$lines = env('APP_ENV') === 'testing' && env('RELEASE_PUBLIC_KEYS') !== null
    ? explode(',', (string) env('RELEASE_PUBLIC_KEYS'))
    : (is_file($shipped) ? (file($shipped, FILE_IGNORE_NEW_LINES) ?: []) : []);

return [

    'public_keys' => array_values(array_filter(
        array_map('trim', $lines),
        fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
    )),

];

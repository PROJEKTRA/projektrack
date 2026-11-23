<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

/*
 * Issues licenses on a trusted machine of the licensor (S-0974). Not part of a
 * release; the private key never reaches an instance, the database or the
 * repository. The key file is encrypted with LICENSE_KEY_PASSPHRASE.
 *
 *   php ee/licensing/issue.php keygen <kid> <key file>
 *   php ee/licensing/issue.php issue <key file> <payload.json>
 *
 * keygen prints the entry for config/license-keys.php. issue reads the payload
 * of ADR-0003 (kid and issued_at are set here), checks it as an instance would
 * and prints the license.
 */

use App\Licensing\LicenseKeys;
use App\Licensing\LicenseToken;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function stop(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(1);
}

function keyFrom(#[SensitiveParameter] string $passphrase, string $salt): string
{
    return sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $passphrase, $salt,
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
}

$passphrase = getenv('LICENSE_KEY_PASSPHRASE');
if (! is_string($passphrase) || strlen($passphrase) < 16) {
    stop('Set LICENSE_KEY_PASSPHRASE to at least 16 characters.');
}

[, $command, $first, $second] = array_pad(array_map(strval(...), $argv), 4, '');

if ($command === 'keygen' && preg_match('/^[a-z0-9-]{3,40}$/', $first) === 1 && $second !== '') {
    $handle = @fopen($second, 'x') ?: stop('The key file exists already or cannot be written.');
    chmod($second, 0600);
    $pair = sodium_crypto_sign_keypair();
    $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $box = sodium_crypto_secretbox(sodium_crypto_sign_secretkey($pair), $nonce, keyFrom($passphrase, $salt));
    fwrite($handle, json_encode(['kid' => $first, 'salt' => base64_encode($salt), 'nonce' => base64_encode($nonce), 'key' => base64_encode($box)], JSON_THROW_ON_ERROR)."\n");
    echo "['kid' => '{$first}', 'public_key' => '".base64_encode(sodium_crypto_sign_publickey($pair))."',\n"
        ." 'valid_from' => '".gmdate('Y-m-d\T00:00:00\Z')."', 'valid_until' => '".gmdate('Y-m-d\T00:00:00\Z', time() + 400 * 86400)."', 'status' => 'active'],\n";
    exit(0);
}

if ($command !== 'issue' || ! is_file($first) || ! is_file($second)) {
    stop('Usage: issue.php keygen <kid> <key file> | issue.php issue <key file> <payload.json>');
}

$file = json_decode((string) file_get_contents($first), true);
$part = fn (string $name): string => is_array($file) && is_string($file[$name] ?? null) ? (string) base64_decode($file[$name], true) : '';
if (! is_array($file) || ! is_string($file['kid'] ?? null) || strlen($part('salt')) !== SODIUM_CRYPTO_PWHASH_SALTBYTES || strlen($part('nonce')) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
    stop('This is not a license key file.');
}
$secret = sodium_crypto_secretbox_open($part('key'), $part('nonce'), keyFrom($passphrase, $part('salt')));
if (! is_string($secret) || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    stop('The passphrase is wrong or the key file is damaged.');
}

$payload = json_decode((string) file_get_contents($second), true);
if (! is_array($payload) || array_is_list($payload)) {
    stop('The payload is not a JSON object.');
}
$payload['kid'] = $file['kid'];
$payload['issued_at'] = gmdate('Y-m-d\TH:i:s\Z');

try {
    $token = LicenseToken::encode($payload, $secret);
    LicenseToken::decode($token, new LicenseKeys([[
        'kid' => $file['kid'],
        'public_key' => base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret)),
        'valid_from' => '2000-01-01T00:00:00Z', 'valid_until' => '2100-01-01T00:00:00Z', 'status' => 'active',
    ]]));
} catch (Throwable $e) {
    stop('Not issued: '.$e->getMessage());
}
echo $token."\n";

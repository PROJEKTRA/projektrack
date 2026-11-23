<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Release\ReleaseVerifier;
use Illuminate\Support\Str;

/*
 * S-0924: a release file is accepted only with a checksum list signed by a
 * trusted Ed25519 release key and a matching checksum.
 */

/**
 * A signed release in a temporary folder.
 *
 * @return array{dir: string, zip: string, public: string, secret: string}
 */
function signedRelease(): array
{
    $dir = sys_get_temp_dir().'/release-'.Str::random(12);
    mkdir($dir);
    $zip = $dir.'/projektrack-0.9.0.zip';
    file_put_contents($zip, 'release content '.Str::random(32));
    file_put_contents($dir.'/projektrack-0.9.0.cdx.json', '{"bomFormat":"CycloneDX"}');

    $pair = sodium_crypto_sign_keypair();
    $sums = hash_file('sha256', $zip).'  projektrack-0.9.0.zip'."\n"
        .hash_file('sha256', $dir.'/projektrack-0.9.0.cdx.json').'  projektrack-0.9.0.cdx.json'."\n";
    file_put_contents($dir.'/SHA256SUMS', $sums);
    file_put_contents($dir.'/SHA256SUMS.sig', sodium_crypto_sign_detached($sums, sodium_crypto_sign_secretkey($pair)));

    return [
        'dir' => $dir,
        'zip' => $zip,
        'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
        'secret' => sodium_crypto_sign_secretkey($pair),
    ];
}

function verifyRelease(array $release, ?array $keys = null): ?string
{
    return (new ReleaseVerifier($keys ?? [$release['public']]))
        ->verify($release['zip'], $release['dir'].'/SHA256SUMS', $release['dir'].'/SHA256SUMS.sig');
}

it('accepts a genuine release', function () {
    expect(verifyRelease(signedRelease()))->toBeNull();
});

it('accepts a base64 signature and a key among several (rotation)', function () {
    $release = signedRelease();
    file_put_contents($release['dir'].'/SHA256SUMS.sig', base64_encode((string) file_get_contents($release['dir'].'/SHA256SUMS.sig')));
    $other = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));

    expect(verifyRelease($release, [$other, $release['public']]))->toBeNull();
});

it('refuses a ZIP changed after signing', function () {
    $release = signedRelease();
    file_put_contents($release['zip'], 'x', FILE_APPEND);

    expect(verifyRelease($release))->toContain('was changed after signing');
});

it('refuses a checksum list changed after signing', function () {
    $release = signedRelease();
    file_put_contents($release['zip'], 'replaced content');
    $list = (string) file_get_contents($release['dir'].'/SHA256SUMS');
    file_put_contents($release['dir'].'/SHA256SUMS', preg_replace('/^[0-9a-f]{64}/', hash_file('sha256', $release['zip']), $list));

    expect(verifyRelease($release))->toContain('not signed by a trusted release key');
});

it('refuses a list signed with another key', function () {
    $release = signedRelease();
    $foreign = sodium_crypto_sign_keypair();
    file_put_contents($release['dir'].'/SHA256SUMS.sig', sodium_crypto_sign_detached((string) file_get_contents($release['dir'].'/SHA256SUMS'), sodium_crypto_sign_secretkey($foreign)));

    expect(verifyRelease($release))->toContain('not signed by a trusted release key');
});

it('refuses when no trusted key is configured or a key is malformed', function (array $keys) {
    expect(verifyRelease(signedRelease(), $keys))->toBe('No trusted release key is configured.');
})->with([
    'none' => [[]],
    'not base64' => [['not a key!']],
    'wrong length' => [[base64_encode('short')]],
]);

it('refuses a file the signed list does not name, or names twice', function () {
    $release = signedRelease();
    $renamed = $release['dir'].'/projektrack-0.9.1.zip';
    copy($release['zip'], $renamed);

    expect((new ReleaseVerifier([$release['public']]))->verify($renamed, $release['dir'].'/SHA256SUMS', $release['dir'].'/SHA256SUMS.sig'))
        ->toContain('does not name');

    $twice = hash_file('sha256', $release['zip']).'  projektrack-0.9.0.zip'."\n".str_repeat('0', 64).'  projektrack-0.9.0.zip'."\n";
    file_put_contents($release['dir'].'/SHA256SUMS', $twice);
    file_put_contents($release['dir'].'/SHA256SUMS.sig', sodium_crypto_sign_detached($twice, $release['secret']));

    expect(verifyRelease($release))->toContain('does not name');
});

it('refuses a signature file that is no signature', function () {
    $release = signedRelease();
    file_put_contents($release['dir'].'/SHA256SUMS.sig', 'garbage');

    expect(verifyRelease($release))->toBe('The signature file is not an Ed25519 signature.');
});

it('verifies from the command line with the configured keys', function () {
    $release = signedRelease();
    config(['release.public_keys' => [$release['public']]]);

    $this->artisan('projektrack:release:verify', ['file' => $release['zip']])
        ->expectsOutputToContain('is a genuine release')
        ->assertSuccessful();

    file_put_contents($release['zip'], 'x', FILE_APPEND);

    $this->artisan('projektrack:release:verify', ['file' => $release['zip']])
        ->expectsOutputToContain('Refused')
        ->assertFailed();
});

it('ships no key by default, so nothing is trusted unless configured', function () {
    $this->artisan('projektrack:release:verify', ['file' => signedRelease()['zip']])
        ->expectsOutputToContain('No trusted release key')
        ->assertFailed();
})->skip(fn (): bool => config('release.public_keys') !== [], 'A shipped release key is configured.');

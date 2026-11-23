<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Licensing\InstanceId;
use App\Licensing\InstanceLicense;
use App\Licensing\InvalidLicense;
use App\Licensing\License;
use App\Licensing\LicenseKeys;
use App\Licensing\LicenseStatus;
use App\Licensing\LicenseToken;
use App\Modules\Edition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
 * S-0974: signed licenses, validated offline (ADR-0003 section 3).
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-02T10:00:00Z'));
    $this->file = sys_get_temp_dir().'/instance-id-'.bin2hex(random_bytes(6));
    app()->bind(InstanceId::class, fn () => new InstanceId($this->file));
    $this->pair = sodium_crypto_sign_keypair();
    trustKeys([licenseKey('ptk-2026-01', $this->pair)]);
});

afterEach(fn () => @unlink($this->file));

function licenseKey(string $kid, string $pair, string $status = 'active', string $from = '2026-11-01T00:00:00Z', string $until = '2027-12-31T00:00:00Z'): array
{
    return ['kid' => $kid, 'public_key' => base64_encode(sodium_crypto_sign_publickey($pair)), 'valid_from' => $from, 'valid_until' => $until, 'status' => $status];
}

function trustKeys(array $keys): void
{
    config(['license-keys.keys' => $keys]);
    app()->forgetInstance(InstanceLicense::class);
}

function licensePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'addons' => ['white_label'],
        'binding' => ['domains' => ['projects.customer-example.test'], 'instance_id' => app(InstanceId::class)->get()],
        'check_in' => ['interval_hours' => 24, 'required' => false],
        'customer' => ['id' => 'lc_000123', 'name' => 'Primjer d.o.o. ŠĐČ'],
        'edition' => 'business',
        'expires_at' => '2027-12-01T00:00:00Z',
        'features' => ['servicedesk'],
        'grace' => ['expiry_days' => 14, 'over_seat_days' => 30, 'pending_payment_days' => 14],
        'issued_at' => '2026-12-01T09:00:00Z',
        'iss' => 'projektra-licensing',
        'kid' => 'ptk-2026-01',
        'license_id' => 'lic_01JD8QA2H6W4C8E0G2J4L6N8Q0',
        'not_before' => '2026-12-01T00:00:00Z',
        'plan' => 'founding_customer_business',
        'seats' => ['full' => 12, 'light' => 5],
        'serial' => 3,
        'status' => 'active',
        'v' => 1,
    ], $overrides);
}

function licenseToken(array $overrides = [], ?string $pair = null): string
{
    return LicenseToken::encode(licensePayload($overrides), sodium_crypto_sign_secretkey($pair ?? test()->pair));
}

function decodeLicense(string $token): License
{
    return LicenseToken::decode($token, app(LicenseKeys::class));
}

function rawToken(string $json): string
{
    $url = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    $input = 'PTKL1.'.$url($json);

    return $input.'.'.$url(sodium_crypto_sign_detached($input, sodium_crypto_sign_secretkey(test()->pair)));
}

describe('token', function () {
    it('carries edition, seats, expiry, binding and add-ons', function () {
        $license = decodeLicense(licenseToken());

        expect($license->edition)->toBe(Edition::Business)
            ->and($license->fullSeats)->toBe(12)
            ->and($license->lightSeats)->toBe(5)
            ->and($license->expiresAt->toIso8601ZuluString())->toBe('2027-12-01T00:00:00Z')
            ->and($license->instanceId)->toBe(app(InstanceId::class)->get())
            ->and($license->coversDomain('Projects.customer-example.test'))->toBeTrue()
            ->and($license->coversDomain('other.test'))->toBeFalse()
            ->and($license->addons)->toBe(['white_label'])
            ->and($license->customer)->toBe('Primjer d.o.o. ŠĐČ');
    });

    it('refuses a changed seat count', function () {
        [$prefix, , $signature] = explode('.', licenseToken());
        $forged = explode('.', licenseToken(['seats' => ['full' => 500]]))[1];

        expect(fn () => decodeLicense("{$prefix}.{$forged}.{$signature}"))->toThrow(InvalidLicense::class, 'signature');
    });

    it('refuses every single changed character', function () {
        $token = licenseToken();

        foreach (range(0, strlen($token) - 1, 7) as $i) {
            $changed = substr_replace($token, $token[$i] === 'A' ? 'B' : 'A', $i, 1);
            expect(fn () => decodeLicense($changed))->toThrow(InvalidLicense::class);
        }
    });

    it('refuses what is not a license', function (string $token) {
        expect(fn () => decodeLicense($token))->toThrow(InvalidLicense::class);
    })->with(['', 'PTKL1', 'PTKL1..', 'PTKL2.e30.e30', 'a.b.c', 'PTKL1.e30=.e30', str_repeat('A', 20000)]);

    it('refuses a payload that is not canonical', function (string $json) {
        expect(fn () => decodeLicense(rawToken($json)))->toThrow(InvalidLicense::class, 'canonical');
    })->with([
        'spaces' => '{"issued_at":"2026-12-01T09:00:00Z", "kid":"ptk-2026-01"}',
        'order' => '{"kid":"ptk-2026-01","issued_at":"2026-12-01T09:00:00Z"}',
        'duplicate key' => '{"a":1,"a":2,"issued_at":"2026-12-01T09:00:00Z","kid":"ptk-2026-01"}',
        'float' => '{"a":1.5,"issued_at":"2026-12-01T09:00:00Z","kid":"ptk-2026-01"}',
        'escaped letter' => '{"a":"\u0041","issued_at":"2026-12-01T09:00:00Z","kid":"ptk-2026-01"}',
    ]);

    it('refuses invalid content even when signed', function (array $overrides) {
        expect(fn () => decodeLicense(licenseToken($overrides)))->toThrow(InvalidLicense::class);
    })->with([
        'issuer' => [['iss' => 'someone']],
        'version' => [['v' => 2]],
        'community' => [['edition' => 'community']],
        'unknown edition' => [['edition' => 'gold']],
        'negative seats' => [['seats' => ['full' => -1]]],
        'text seats' => [['seats' => ['full' => '12']]],
        'serial zero' => [['serial' => 0]],
        'unknown status' => [['status' => 'free']],
        'pending without end' => [['status' => 'pending_payment']],
        'impossible date' => [['expires_at' => '2027-02-30T00:00:00Z']],
        'other time zone' => [['expires_at' => '2027-12-01T00:00:00+01:00']],
        'ends before it starts' => [['expires_at' => '2026-11-30T00:00:00Z']],
        'endless grace' => [['grace' => ['expiry_days' => 100000]]],
        'license id' => [['license_id' => 'lic_x']],
        'status end of a wrong type' => [['status_until' => 5]],
        'no instance' => [['binding' => ['instance_id' => '']]],
        'add-ons not a list of texts' => [['addons' => [1]]],
    ]);
});

describe('keys', function () {
    it('refuses without keys, with another key and with a revoked key', function () {
        $token = licenseToken();

        foreach ([[], [licenseKey('ptk-2026-01', sodium_crypto_sign_keypair())], [licenseKey('ptk-2026-01', $this->pair, 'revoked')], [licenseKey('ptk-2026-01', $this->pair), licenseKey('ptk-2026-01', $this->pair, 'revoked')], [['kid' => 'ptk-2026-01']], ['x']] as $keys) {
            trustKeys($keys);
            expect(fn () => decodeLicense($token))->toThrow(InvalidLicense::class);
        }
    });

    it('accepts two keys during a rotation and a retired key for what it signed', function () {
        $next = sodium_crypto_sign_keypair();
        trustKeys([licenseKey('ptk-2026-01', $this->pair, 'retired'), licenseKey('ptk-2027-01', $next)]);

        expect(decodeLicense(licenseToken())->kid)->toBe('ptk-2026-01')
            ->and(decodeLicense(licenseToken(['kid' => 'ptk-2027-01'], $next))->kid)->toBe('ptk-2027-01');
        expect(fn () => decodeLicense(licenseToken(['kid' => 'ptk-2027-01'])))->toThrow(InvalidLicense::class);
    });

    it('refuses a license issued outside the validity of its key', function (string $issued) {
        expect(fn () => decodeLicense(licenseToken(['issued_at' => $issued])))->toThrow(InvalidLicense::class);
    })->with(['2026-10-31T23:59:59Z', '2027-12-31T00:00:00Z']);

    it('ships no key by default', function () {
        expect(require config_path('license-keys.php'))->toBe(['keys' => []]);
    })->skip(fn () => (require dirname(__DIR__, 3).'/config/license-keys.php')['keys'] !== [], 'Keys are shipped.');
});

it('computes the state at its exact limits', function (array $overrides, string $time, LicenseStatus $status) {
    $license = decodeLicense(licenseToken($overrides));

    expect($license->status(CarbonImmutable::parse($time)))->toBe($status);
})->with([
    'before start' => [[], '2026-11-30T23:59:59Z', LicenseStatus::NotYetValid],
    'start' => [[], '2026-12-01T00:00:00Z', LicenseStatus::Active],
    'last second' => [[], '2027-11-30T23:59:59Z', LicenseStatus::Active],
    'expired' => [[], '2027-12-01T00:00:00Z', LicenseStatus::Grace],
    'last second of grace' => [[], '2027-12-14T23:59:59Z', LicenseStatus::Grace],
    'grace over' => [[], '2027-12-15T00:00:00Z', LicenseStatus::ReadOnly],
    'same instant in another zone' => [[], '2027-12-01T01:00:00+01:00', LicenseStatus::Grace],
    'leap day' => [['expires_at' => '2028-02-29T00:00:00Z'], '2028-03-13T23:59:59Z', LicenseStatus::Grace],
    'after the leap day' => [['expires_at' => '2028-02-29T00:00:00Z'], '2028-03-14T00:00:00Z', LicenseStatus::ReadOnly],
    'payment pending' => [['status' => 'pending_payment', 'status_until' => '2026-12-15T23:59:59Z'], '2026-12-15T23:59:59Z', LicenseStatus::PendingPayment],
    'payment overdue' => [['status' => 'pending_payment', 'status_until' => '2026-12-15T23:59:59Z'], '2026-12-16T00:00:00Z', LicenseStatus::ReadOnly],
    'trial' => [['status' => 'trial', 'status_until' => '2026-12-31T00:00:00Z'], '2026-12-05T00:00:00Z', LicenseStatus::Active],
    'trial over' => [['status' => 'trial', 'status_until' => '2026-12-31T00:00:00Z'], '2027-01-01T00:00:00Z', LicenseStatus::ReadOnly],
]);

describe('instance', function () {
    it('is Community without a license', function () {
        expect(app(InstanceLicense::class)->current())->toBeNull()
            ->and(app(InstanceLicense::class)->status())->toBeNull();
    });

    it('keeps one identity, also from the file after a lost database row', function () {
        $id = app(InstanceId::class)->get();
        expect($id)->toMatch('/^inst_[0-9A-Z]{26}$/')->and(trim((string) file_get_contents($this->file)))->toBe($id);

        DB::table('instance_state')->delete();
        expect(app(InstanceId::class)->get())->toBe($id);
    });

    it('repairs the file and survives a damaged id', function () {
        $id = app(InstanceId::class)->get();
        file_put_contents($this->file, 'inst_');
        expect(app(InstanceId::class)->get())->toBe($id)->and(trim((string) file_get_contents($this->file)))->toBe($id);

        app(InstanceLicense::class)->install(licenseToken());
        DB::table('instance_state')->update(['instance_id' => 'broken']);
        app()->forgetInstance(InstanceLicense::class);
        expect(app(InstanceLicense::class)->current())->toBeNull()
            ->and(app(InstanceLicense::class)->status())->toBe(LicenseStatus::Invalid);
        $this->artisan('projektrack:license:show')->assertFailed();
    });

    it('keeps line separators and literal escapes canonical', function (string $name) {
        expect(decodeLicense(licenseToken(['customer' => ['name' => $name]]))->customer)->toBe($name);
    })->with(['a\u2028b', "a\u{2028}b", 'quote " slash / back \\']);

    it('installs a license and keeps it when a forged one is refused', function () {
        $licenses = app(InstanceLicense::class);
        $licenses->install(licenseToken()."\n");

        expect(fn () => $licenses->install(licenseToken(['serial' => 4], sodium_crypto_sign_keypair())))->toThrow(InvalidLicense::class);
        expect($licenses->current()?->serial)->toBe(3)->and($licenses->status())->toBe(LicenseStatus::Active);
    });

    it('refuses the license of another installation and one that has ended', function () {
        $licenses = app(InstanceLicense::class);

        expect(fn () => $licenses->install(licenseToken(['binding' => ['instance_id' => 'inst_01JD8Q7Z5M3K9V2X4B6N8P0R1T']])))->toThrow(InvalidLicense::class, 'another installation');
        $licenses->install(licenseToken());
        expect(fn () => $licenses->install(licenseToken(['serial' => 4, 'not_before' => '2027-01-01T00:00:00Z'])))->toThrow(InvalidLicense::class, 'not valid yet');
        expect($licenses->current()?->serial)->toBe(3);
        $this->travelTo(CarbonImmutable::parse('2028-01-01T00:00:00Z'));
        expect(fn () => $licenses->install(licenseToken()))->toThrow(InvalidLicense::class, 'already ended');
    });

    it('never goes back to an earlier issue, not even over another license', function () {
        $licenses = app(InstanceLicense::class);
        $licenses->install(licenseToken(['serial' => 5]));
        $licenses->install(licenseToken(['serial' => 5]));

        expect(fn () => $licenses->install(licenseToken(['serial' => 4, 'seats' => ['full' => 500]])))->toThrow(InvalidLicense::class, 'newer issue');
        expect(fn () => $licenses->install(licenseToken(['serial' => 5, 'seats' => ['full' => 500]])))->toThrow(InvalidLicense::class, 'newer issue');

        $licenses->install(licenseToken(['license_id' => 'lic_01JD8QA2H6W4C8E0G2J4L6N8Q1', 'serial' => 1]));
        expect($licenses->current()?->id)->toBe('lic_01JD8QA2H6W4C8E0G2J4L6N8Q1');
        expect(fn () => $licenses->install(licenseToken(['serial' => 4])))->toThrow(InvalidLicense::class, 'newer issue');
        expect($licenses->current()?->id)->toBe('lic_01JD8QA2H6W4C8E0G2J4L6N8Q1')
            ->and(DB::table('instance_licenses')->where('is_active', true)->count())->toBe(1);
    });

    it('counts a changed row as no license', function (array $change) {
        app(InstanceLicense::class)->install(licenseToken());
        DB::table('instance_licenses')->update($change);
        app()->forgetInstance(InstanceLicense::class);

        expect(app(InstanceLicense::class)->current())->toBeNull()
            ->and(app(InstanceLicense::class)->status())->toBe(LicenseStatus::Invalid)
            ->and(LicenseStatus::Invalid->allowsUse())->toBeFalse();
        $this->artisan('projektrack:license:show')->expectsOutputToContain('not valid')->assertSuccessful();
    })->with([
        'token' => [['token' => 'PTKL1.e30.e30']],
        'serial' => [['serial' => 1]],
        'id' => [['license_id' => 'lic_01JD8QA2H6W4C8E0G2J4L6N8Q9']],
    ]);

    it('loses the license when its key is revoked', function () {
        app(InstanceLicense::class)->install(licenseToken());
        trustKeys([licenseKey('ptk-2026-01', $this->pair, 'revoked')]);

        expect(app(InstanceLicense::class)->current())->toBeNull();
    });

    it('warns about a clock far behind, without changing the license', function () {
        $licenses = app(InstanceLicense::class);
        $licenses->install(licenseToken());
        expect($licenses->clockProblem())->toBeFalse();

        $this->travelTo(CarbonImmutable::parse('2026-12-02T09:00:00Z'));
        expect($licenses->clockProblem())->toBeFalse();
        $this->travelTo(CarbonImmutable::parse('2026-11-28T10:00:00Z'));
        expect($licenses->clockProblem())->toBeTrue();
    });
});

describe('commands', function () {
    it('installs from a file, records it, and shows it', function () {
        file_put_contents($this->file.'.lic', licenseToken());

        $this->artisan('projektrack:license:install', ['file' => $this->file.'.lic'])->expectsOutputToContain('installed for Primjer')->assertSuccessful();
        $this->artisan('projektrack:license:show')->expectsOutputToContain('business')->assertSuccessful();
        expect(DB::table('audit_log')->where('action', 'license.installed')->exists())->toBeTrue();
        unlink($this->file.'.lic');
    });

    it('refuses a bad file and a missing file', function () {
        file_put_contents($this->file.'.lic', 'x');

        $this->artisan('projektrack:license:install', ['file' => $this->file.'.lic'])->expectsOutputToContain('Not installed')->assertFailed();
        $this->artisan('projektrack:license:install', ['file' => '/nowhere'])->assertFailed();
        unlink($this->file.'.lic');
    });

    it('issues with the tool of the licensor what the instance accepts', function () {
        $dir = sys_get_temp_dir().'/lic-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $run = function (array $args, string $passphrase = 'correct horse battery'): array {
            $process = new Process([PHP_BINARY, base_path('ee/licensing/issue.php'), ...$args], null, ['LICENSE_KEY_PASSPHRASE' => $passphrase]);
            $process->run();

            return [$process->getExitCode(), $process->getOutput(), $process->getErrorOutput()];
        };

        [$code, $entry] = $run(['keygen', 'ptk-test', "{$dir}/key.json"]);
        expect($code)->toBe(0);
        preg_match("/'public_key' => '([^']+)'/", $entry, $match);
        trustKeys([['kid' => 'ptk-test', 'public_key' => $match[1], 'valid_from' => '2020-01-01T00:00:00Z', 'valid_until' => '2100-01-01T00:00:00Z', 'status' => 'active']]);
        expect($run(['keygen', 'ptk-test', "{$dir}/key.json"])[0])->toBe(1);

        file_put_contents("{$dir}/payload.json", json_encode(licensePayload()));
        [$code, $token] = $run(['issue', "{$dir}/key.json", "{$dir}/payload.json"]);
        expect($code)->toBe(0)->and(app(InstanceLicense::class)->install($token)->serial)->toBe(3);

        expect($run(['issue', "{$dir}/key.json", "{$dir}/payload.json"], 'a wrong passphrase!')[0])->toBe(1);
        file_put_contents("{$dir}/payload.json", json_encode(licensePayload(['edition' => 'gold'])));
        expect($run(['issue', "{$dir}/key.json", "{$dir}/payload.json"])[0])->toBe(1);
        array_map(unlink(...), glob("{$dir}/*"));
        rmdir($dir);
    })->skip(! is_file(dirname(__DIR__, 3).'/ee/licensing/issue.php'), 'Community edition.');
});

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\I18n\StringCatalog;
use App\Models\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;

/*
 * S-0925 (command line part): installation on plain hosting.
 */

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/install-'.bin2hex(random_bytes(6));
    mkdir($this->dir);
    app()->useEnvironmentPath($this->dir);
    app()->loadEnvironmentFrom('.env');
    // The compiled strings of this test stay out of the project.
    config(['i18n.cache_path' => $this->dir.'/strings']);
    app()->forgetInstance(StringCatalog::class);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function configured(string $url = 'https://app.example.test'): void
{
    file_put_contents(test()->dir.'/.env', "APP_URL={$url}\n");
    config(['app.url' => $url, 'app.debug' => false]);
}

it('writes an environment file with a generated key on the first run and changes nothing else', function () {
    $tenants = Tenant::query()->count();

    $this->artisan('projektrack:install')->expectsOutputToContain('CHANGE-ME')->assertSuccessful();

    $env = (string) file_get_contents($this->dir.'/.env');
    expect($env)->toMatch('#^APP_KEY=base64:[A-Za-z0-9+/]{43}=$#m')
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->and(Tenant::query()->count())->toBe($tenants);
});

it('never overwrites an environment file', function () {
    configured();
    $before = file_get_contents($this->dir.'/.env');

    $this->artisan('projektrack:install')->assertSuccessful();
    expect(file_get_contents($this->dir.'/.env'))->toBe($before);
});

it('refuses with a plain reason', function (array $config, string $reason) {
    configured();
    config($config);

    $this->artisan('projektrack:install')->expectsOutputToContain($reason)->assertFailed();
})->with([
    'placeholder' => [['app.url' => 'https://CHANGE-ME'], 'APP_URL is not the address'],
    'no address' => [['app.url' => ''], 'APP_URL is not the address'],
    'host without a dot' => [['app.url' => 'http://localhost'], 'host name with a dot'],
    'address' => [['app.url' => 'http://127.0.0.1:8000'], 'host name with a dot'],
    'http' => [['app.url' => 'http://app.example.com'], 'must start with https'],
    'debug' => [['app.url' => 'https://app.example.com', 'app.debug' => true], 'APP_DEBUG must be false'],
    'no key' => [['app.key' => ''], 'APP_KEY is empty'],
    'broken key' => [['app.key' => 'base64:CHANGE-ME'], 'APP_KEY is not a valid key'],
    'test mode on a reachable host' => [['app.url' => 'https://app.example.com', 'app.env' => 'staging'], 'APP_ENV must be production'],
    'mail placeholder' => [['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'CHANGE-ME'], 'MAIL_HOST is not filled in'],
]);

it('keeps the data of an existing installation', function () {
    configured();
    Notification::fake();

    $this->artisan('projektrack:install')->expectsOutputToContain('The data is kept')->assertSuccessful();
    Notification::assertNothingSent();
});

// The first installation itself is tested on Apache (tests/amp/install.sh).

it('drains the database queue from the one cron entry, and only that queue', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'queue:work'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *');

    config(['queue.default' => 'database']);
    expect($event->filtersPass(app()))->toBeTrue();
    config(['queue.default' => 'redis']);
    expect($event->filtersPass(app()))->toBeFalse();
});

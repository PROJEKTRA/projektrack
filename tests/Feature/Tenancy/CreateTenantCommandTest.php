<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use App\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * S-0923: the first tenant of a new instance, created by the operator.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function tenantOptions(array $overrides = []): array
{
    return array_merge([
        '--slug' => 'acme',
        '--name' => 'Acme d.o.o.',
        '--domain' => 'app.acme.example.test',
        '--admin-email' => 'admin@acme.example.test',
        '--admin-name' => 'Ana Admin',
    ], $overrides);
}

it('creates the tenant, its address and an administrator who gets a setup link', function () {
    Notification::fake();

    $this->artisan('projektrack:tenant:create', tenantOptions(['--domain' => 'App.Acme.Example.Test.']))
        ->expectsOutputToContain('Tenant acme created at app.acme.example.test.')
        ->assertSuccessful();

    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    expect($tenant->name)->toBe('Acme d.o.o.')
        ->and($tenant->status)->toBe('active')
        ->and($tenant->default_locale)->toBe('bs')
        ->and(app(TenantResolver::class)->forHost('app.acme.example.test')?->id)->toBe($tenant->id);

    app(TenantContext::class)->run($tenant, function () {
        $admin = User::query()->where('email', 'admin@acme.example.test')->firstOrFail();

        expect($admin->roles()->where('key', Role::ADMIN)->exists())->toBeTrue()
            ->and($admin->email_verified_at)->not->toBeNull();

        Notification::assertSentTo($admin, ResetPasswordLink::class);
        expect(DB::table('audit_log')->where('tenant_id', $admin->tenant_id)->where('action', 'tenant.created')->exists())->toBeTrue();
    });
});

it('never prints the setup link', function () {
    Notification::fake();

    $this->artisan('projektrack:tenant:create', tenantOptions())
        ->doesntExpectOutputToContain('reset-password')
        ->doesntExpectOutputToContain('token')
        ->assertSuccessful();
});

it('refuses to run in production without mail delivery', function (string $mailer, ?string $host) {
    Notification::fake();
    app()->detectEnvironment(fn (): string => 'production');
    config(['mail.default' => $mailer, 'mail.mailers.smtp.host' => $host]);

    $this->artisan('projektrack:tenant:create', tenantOptions())
        ->expectsOutputToContain('Mail delivery is not configured')
        ->assertFailed();

    expect(Tenant::query()->where('slug', 'acme')->exists())->toBeFalse();
    Notification::assertNothingSent();
})->with([
    'log mailer' => ['log', 'mail.example.test'],
    'array mailer' => ['array', 'mail.example.test'],
    'smtp without host' => ['smtp', ''],
]);

it('refuses a slug or host name in use', function (array $options, string $message) {
    Notification::fake();
    Tenant::factory()->withDomain('taken.example.test')->create(['slug' => 'taken']);

    $this->artisan('projektrack:tenant:create', tenantOptions($options))
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Tenant::query()->where('slug', 'acme')->exists())->toBeFalse();
})->with([
    'slug' => [['--slug' => 'taken'], 'A tenant with this slug exists already.'],
    'host' => [['--domain' => 'taken.example.test'], 'This host name is in use already.'],
]);

it('refuses invalid input and creates nothing', function (array $options) {
    Notification::fake();
    $before = TenantDomain::withoutGlobalScope(TenantScope::class)->count();

    $this->artisan('projektrack:tenant:create', tenantOptions($options))->assertFailed();

    expect(Tenant::query()->where('slug', 'acme')->exists())->toBeFalse()
        ->and(TenantDomain::withoutGlobalScope(TenantScope::class)->count())->toBe($before);
    Notification::assertNothingSent();
})->with([
    'missing name' => [['--name' => '']],
    'slug with spaces' => [['--slug' => 'acme company']],
    'host with a path' => [['--domain' => 'app.example.test/login']],
    'host with a port' => [['--domain' => 'app.example.test:8080']],
    'single-label host' => [['--domain' => 'intranet']],
    'invalid address' => [['--admin-email' => 'not-an-address']],
    'unknown locale' => [['--locale' => 'zz']],
    'lower-case country' => [['--country' => 'ba']],
    'unknown time zone' => [['--timezone' => 'Mars/Olympus']],
]);

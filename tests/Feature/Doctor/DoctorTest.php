<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Doctor\Doctor;
use App\Doctor\Finding;
use App\Http\Middleware\SetSecurityHeaders;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;

/*
 * S-0988: projektrack doctor checks a live installation from outside.
 */

const DOCTOR_BASE = 'https://work.example.com';

/** Headers of a well-configured installation. */
function goodHeaders(): array
{
    return [
        'X-Robots-Tag' => SetSecurityHeaders::ROBOTS,
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none'",
        'Strict-Transport-Security' => 'max-age=31536000',
        'Server' => 'Apache',
    ];
}

/** @param array<string, mixed> $overrides url pattern => response */
function fakeInstance(array $overrides = []): void
{
    // Overrides replace defaults; the catch-all stays last, because the first match wins.
    $routes = [
        DOCTOR_BASE.'/login' => Http::response('<html><title>Sign in</title></html>', 200, goodHeaders()),
        DOCTOR_BASE.'/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200, goodHeaders()),
        DOCTOR_BASE.'/health' => Http::response('OK', 200, goodHeaders()),
        DOCTOR_BASE.'/build/*' => Http::response('x', 200, goodHeaders()),
        ...$overrides,
    ];
    $catchAll = $routes['*'] ?? Http::response('<html>Not found</html>', 404, goodHeaders());
    unset($routes['*']);

    Http::fake([...$routes, '*' => $catchAll]);
}

/** A production server: debug off, production environment. */
function inProduction(): void
{
    config(['app.debug' => false]);
    app()->detectEnvironment(fn (): string => 'production');
}

/** @param list<Finding> $findings */
function failing(array $findings): array
{
    return array_values(array_map(fn (Finding $f): string => $f->check, array_filter($findings, fn (Finding $f): bool => ! $f->ok)));
}

function doctorAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());

    return $admin;
}

it('finds nothing wrong on a well-configured installation', function () {
    inProduction();
    fakeInstance();

    expect(failing(app(Doctor::class)->run(DOCTOR_BASE)))->toBe([]);
});

it('reports what a visitor could see or reach', function (array $override, string $check) {
    inProduction();
    fakeInstance($override);

    expect(failing(app(Doctor::class)->run(DOCTOR_BASE)))->toContain($check);
})->with([
    'no robots header' => [fn () => [DOCTOR_BASE.'/login' => Http::response('<html></html>', 200, ['Referrer-Policy' => 'no-referrer'])], 'robots_header'],
    'server version' => [fn () => [DOCTOR_BASE.'/login' => Http::response('<html></html>', 200, [...goodHeaders(), 'Server' => 'Apache/2.4.58 (Ubuntu)'])], 'server_signature'],
    'powered by' => [fn () => [DOCTOR_BASE.'/login' => Http::response('<html></html>', 200, [...goodHeaders(), 'X-Powered-By' => 'PHP/8.4.1'])], 'powered_by'],
    'product name' => [fn () => [DOCTOR_BASE.'/login' => Http::response('<html>Welcome to Projektrack</html>', 200, goodHeaders())], 'fingerprint'],
    'product name on 404' => [fn () => ['*' => Http::response('<html>Laravel</html>', 404, goodHeaders())], 'fingerprint'],
    'sign-in page fails' => [fn () => [DOCTOR_BASE.'/login' => Http::response('Not Found', 404, goodHeaders())], 'reachable'],
    'robots.txt allows' => [fn () => [DOCTOR_BASE.'/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200, goodHeaders())], 'robots_txt'],
    '.env readable' => [fn () => [DOCTOR_BASE.'/.env' => Http::response('APP_KEY=base64:x', 200)], 'web_root'],
    '.git readable' => [fn () => [DOCTOR_BASE.'/.git/config' => Http::response('[core]', 200)], 'web_root'],
    'database readable' => [fn () => [DOCTOR_BASE.'/database/database.sqlite' => Http::response('SQLite format 3', 200)], 'web_root'],
    'stack trace on 404' => [fn () => ['*' => Http::response('Error #0 /var/www/app/Http/Kernel.php:12', 500)], 'error_pages'],
    'static files without headers' => [fn () => [DOCTOR_BASE.'/build/*' => Http::response('x', 200)], 'asset_headers'],
    'health check gone' => [fn () => [DOCTOR_BASE.'/health' => Http::response('Not Found', 404)], 'health'],
]);

it('flags debug output and a development environment on a public server only', function () {
    fakeInstance();
    config(['app.debug' => true]);
    app()->detectEnvironment(fn (): string => 'local');

    expect(failing(app(Doctor::class)->run(DOCTOR_BASE)))->toContain('debug', 'environment')
        ->and(failing(app(Doctor::class)->run('http://work.example.test')))->not->toContain('debug', 'environment', 'https');
});

it('accepts a refused health check only when the operator restricted it', function () {
    inProduction();
    fakeInstance([DOCTOR_BASE.'/health' => Http::response('Not Found', 404)]);
    config(['health.allowed_ips' => '10.0.0.0/8']);

    expect(failing(app(Doctor::class)->run(DOCTOR_BASE)))->not->toContain('health');
});

it('checks the address of the operator tenant, not APP_URL', function () {
    Http::fake(['*' => Http::response('', 404)]);

    app(Doctor::class)->run();

    Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_HOST) === 'localhost'
        && parse_url($request->url(), PHP_URL_PATH) === '/login');
});

it('exits non-zero on critical findings so monitoring can alert', function () {
    fakeInstance([DOCTOR_BASE.'/.env' => Http::response('APP_KEY=x', 200)]);

    $this->artisan('projektrack:doctor', ['--external' => DOCTOR_BASE])->assertFailed();
});

it('logs new critical problems once and keeps the report on a check of another address', function () {
    Log::spy();
    fakeInstance([DOCTOR_BASE.'/.env' => Http::response('APP_KEY=x', 200)]);

    app(Doctor::class)->run(DOCTOR_BASE);
    app(Doctor::class)->run(DOCTOR_BASE);
    Log::shouldHaveReceived('critical')->once();

    $before = app(Doctor::class)->last();
    $this->artisan('projektrack:doctor', ['--external' => 'https://other.example.com']);
    expect(app(Doctor::class)->last())->toEqual($before);
});

it('shows the last report and a banner to administrators of the operator tenant', function () {
    inProduction();
    fakeInstance([DOCTOR_BASE.'/.env' => Http::response('APP_KEY=x', 200)]);
    app(Doctor::class)->run(DOCTOR_BASE);
    app()->detectEnvironment(fn (): string => 'testing');
    $admin = doctorAdmin();

    $this->actingAs($admin)->get('/admin/doctor')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Doctor')
            ->where('findings', fn ($findings) => collect($findings)->contains(fn ($f) => $f['check'] === 'web_root' && $f['ok'] === false && $f['detail'] === '/.env' && $f['fix'] === __('core:doctor.web_root.fix', [], 'en')))
            ->where('doctorAlerts', 1));

    // Other users see neither the page nor the banner.
    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('doctorAlerts', null));
    $this->actingAs(User::factory()->create())->get('/admin/doctor')->assertForbidden();
});

it('hides the installation report from administrators of customer tenants', function () {
    fakeInstance([DOCTOR_BASE.'/.env' => Http::response('APP_KEY=x', 200)]);
    app(Doctor::class)->run(DOCTOR_BASE);
    $admin = doctorAdmin();
    $operator = Tenant::factory()->create();

    // Several tenants and none named: nobody sees it.
    $this->actingAs($admin)->get('/admin/doctor')->assertNotFound();
    $this->actingAs($admin)->post('/admin/doctor')->assertNotFound();
    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('doctorAlerts', null));

    // Another tenant is the operator: still nothing for this one.
    config(['doctor.operator_tenant' => $operator->slug]);
    $this->actingAs($admin)->get('/admin/doctor')->assertNotFound();

    config(['doctor.operator_tenant' => $this->tenant->slug]);
    $this->actingAs($admin)->get('/admin/doctor')->assertOk();
});

it('asks the scheduler for a check, for operator administrators only', function () {
    $this->actingAs(doctorAdmin())->post('/admin/doctor')->assertRedirect();
    expect(Cache::get(Doctor::REQUESTED_KEY))->toBeTrue();

    Cache::forget(Doctor::REQUESTED_KEY);
    $this->actingAs(User::factory()->create())->post('/admin/doctor')->assertForbidden();
    expect(Cache::has(Doctor::REQUESTED_KEY))->toBeFalse();
});

it('runs a requested check once, and only when one was requested', function () {
    fakeInstance();

    $this->artisan('projektrack:doctor', ['--requested' => true])->assertSuccessful();
    expect(app(Doctor::class)->last())->toBeNull();

    Cache::put(Doctor::REQUESTED_KEY, true);
    $this->artisan('projektrack:doctor', ['--requested' => true]);
    expect(app(Doctor::class)->last())->not->toBeNull()
        ->and(Cache::has(Doctor::REQUESTED_KEY))->toBeFalse();
});

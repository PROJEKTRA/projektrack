<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * S-0986: minimal fingerprint (INV-09), nothing in errors (INV-08), a plain
 * health check (INV-10).
 */

/** Words and patterns no anonymous response may contain. */
function fingerprints(): array
{
    return ['/Projektrack/i', '/PROJEKTRA/', '/Laravel/i', '/Symfony/i', '/\bVite\b/', '/\bVue\b/', '/\bInertia\b/', '/\b\d+\.\d+\.\d+\b/'];
}

function assertNoFingerprint(TestResponse $response): void
{
    $content = (string) $response->getContent();

    foreach (fingerprints() as $pattern) {
        expect(preg_match($pattern, $content))->toBe(0, "anonymous response matches {$pattern}");
    }

    expect($response->headers->has('X-Powered-By'))->toBeFalse()
        ->and($response->headers->has('Server'))->toBeFalse();
}

it('reveals nothing about the software to anonymous visitors', function (string $uri) {
    assertNoFingerprint($this->get($uri));
})->with([
    'sign-in page' => ['/login'],
    'unknown page' => ['/does-not-exist'],
    'health check' => ['/health'],
    'robots.txt' => ['/robots.txt'],
    'API without token' => ['/api/v1/me'],
    'unknown API record' => ['/api/v1/nothing/here'],
]);

it('answers the health check with OK only, without session or cookies, optionally by IP', function () {
    $response = $this->get('/health')->assertOk();

    expect($response->getContent())->toBe('OK')
        ->and($response->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($response->headers->getCookies())->toBe([]);

    config(['health.allowed_ips' => '10.0.0.0/8, 192.0.2.7']);
    $this->call('GET', '/health', server: ['REMOTE_ADDR' => '203.0.113.9'])->assertNotFound();
    $this->call('GET', '/health', server: ['REMOTE_ADDR' => '10.1.2.3'])->assertOk();

    // The framework's own status page is gone.
    $this->get('/up')->assertNotFound();
});

it('shows error pages without paths, hosts, traces or other servers', function () {
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('Failure in '.base_path('app/Secret.php').' on db.internal.example'));
    config(['app.debug' => false]);

    foreach (['/_test/boom' => 500, '/nothing' => 404] as $uri => $status) {
        $response = $this->get($uri)->assertStatus($status);
        $content = (string) $response->getContent();

        expect($content)->not->toContain(base_path())
            ->not->toContain('db.internal.example')
            ->not->toContain('RuntimeException')
            ->not->toContain('#0 ')
            ->not->toMatch('/https?:\/\/(?!localhost)/')
            ->toContain('<meta name="robots" content="noindex')
            ->toContain(__('core:errors.'.($status === 500 ? '5xx' : '404').'.title'));
    }

    $api = $this->getJson('/api/v1/_nothing')->assertNotFound();
    expect((string) $api->getContent())->not->toContain('api/v1');
});

it('answers a wrong method like an unknown address', function () {
    $this->put('/login')->assertNotFound();
    $this->deleteJson('/api/v1/me')->assertNotFound();
});

it('names built assets by content hash only', function () {
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

    foreach ($manifest as $entry) {
        expect($entry['file'])->toMatch('/^assets\/[A-Za-z0-9_-]+\.(js|css)$/');
        foreach ($entry['css'] ?? [] as $css) {
            expect($css)->toMatch('/^assets\/[A-Za-z0-9_-]+\.css$/');
        }
    }
});

it('never turns debug output on in production, whatever APP_DEBUG says', function () {
    $env = ['APP_ENV' => $_ENV['APP_ENV'] ?? null, 'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? null];
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
    $_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = 'true';
    $production = require base_path('config/app.php');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
    $local = require base_path('config/app.php');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    expect($production['debug'])->toBeFalse()
        ->and($local['debug'])->toBeTrue();
});

describe('security review findings', function () {
    it('ships a neutral icon, not a framework logo', function () {
        $icon = (string) file_get_contents(public_path('favicon.svg'));
        $logo = (string) file_get_contents(resource_path('js/components/AppLogoIcon.vue'));

        expect($icon)->not->toContain('#FF2D20')->not->toMatch('/laravel/i')
            ->and($logo)->not->toContain('M17.2 5.633');
    });

    it('answers OPTIONS like an unknown path, without listing methods', function () {
        $response = $this->call('OPTIONS', '/login')->assertNotFound();

        expect($response->headers->has('Allow'))->toBeFalse();
    });

    it('gives JSON errors on every path without framework wording', function () {
        foreach (['/does-not-exist' => 404, '/settings/profile' => 401] as $uri => $status) {
            $response = $this->getJson($uri)->assertStatus($status);
            $content = (string) $response->getContent();

            expect($content)->not->toContain('route')->not->toContain('Unauthenticated')->not->toContain('could not be found');
        }

        $this->postJson('/api/v1/notifications/read', [], ['Content-Type' => 'application/json'])->assertUnauthorized();
        $malformed = $this->call('POST', '/api/v1/files', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{"broken":');
        expect((string) $malformed->getContent())->not->toContain('Syntax')->not->toContain(base_path());
    });

    it('shows the maintenance page without anything about the software', function () {
        // The maintenance page is the 503 page. Real maintenance mode would write a file
        // that parallel test processes share, so the 503 comes from a test route.
        Route::middleware('web')->get('/_test/maintenance', fn () => abort(503));
        $response = $this->get('/_test/maintenance')->assertStatus(503);

        expect((string) $response->getContent())->toContain(__('core:errors.503.title'))->not->toMatch('/Laravel/i');
    });
});
it('shows the version to administrators only', function () {
    $this->actingAs(User::factory()->create())->get('/about')
        ->assertInertia(fn ($page) => $page->where('version', null));

    $admin = User::factory()->withTwoFactor()->create();
    app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());
    $this->actingAs($admin)->get('/about')
        ->assertInertia(fn ($page) => $page->where('version', config('app.version')));
});

it('uses a configured generic title before sign-in', function () {
    config(['brand.login_title' => 'Workspace']);
    app()->forgetScopedInstances();

    expect((string) $this->get('/login')->getContent())->toMatch('/<title[^>]*>Workspace<\/title>/');
});

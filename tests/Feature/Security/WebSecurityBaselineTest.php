<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Routing\Router;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
 * S-0909: web security baseline (security baseline section 1).
 */

describe('security headers', function () {
    it('sends HSTS over HTTPS, nosniff, no framing and a strict policy with nonces', function () {
        $response = $this->get('https://localhost/login')->assertOk();
        $csp = (string) $response->headers->get('Content-Security-Policy');

        expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
            ->and($csp)->toContain("frame-ancestors 'none'")
            ->and($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'(;|$)/")
            ->and($csp)->toMatch("/style-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'(;|$)/")
            ->and($csp)->not->toContain('unsafe-inline')
            ->and($csp)->not->toContain('unsafe-eval');
    });

    it('hands the page nonce to scripts that add styles at runtime', function () {
        $response = $this->get('/login');
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $nonce);

        expect($response->getContent())->toContain('<meta property="csp-nonce" nonce="'.$nonce[1].'">');
    });
});

describe('CSRF', function () {
    it('protects every state-changing web route with the CSRF check and exempts none', function () {
        $router = app(Router::class);
        $writes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
            ->reject(fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'api/'));

        expect($writes)->not->toBeEmpty();

        // The web group carries the check; every write route must be in it.
        $web = app(Kernel::class)->getMiddlewareGroups()['web'] ?? [];
        expect($web)->toContain(PreventRequestForgery::class);

        // Give the router the kernel's groups, as a real request does, so groups resolve to classes.
        foreach (app(Kernel::class)->getMiddlewareGroups() as $name => $middleware) {
            $router->middlewareGroup($name, $middleware);
        }

        $outside = $writes
            ->reject(fn (RouteDefinition $route): bool => in_array(PreventRequestForgery::class, $router->resolveMiddleware($router->gatherRouteMiddleware($route)), true))
            ->map(fn (RouteDefinition $route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        expect($outside)->toBe([]);

        expect((new PreventRequestForgery(app(), app('encrypter')))->getExcludedPaths())->toBe([]);
    });

    it('rejects a state-changing request without a valid token', function () {
        // The framework skips the check while unit tests run; this instance does not.
        $middleware = new class(app(), app('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        // A form posted from another site, without the token of this session.
        $request = Request::create('/settings/profile', 'PATCH', ['name' => 'x'], server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        $request->setLaravelSession(app('session.store'));

        expect(fn () => $middleware->handle($request, fn () => response('ok')))->toThrow(TokenMismatchException::class);
    });
});

it('rejects a wrong token without a same-origin header, and from a sibling site', function (array $server) {
    $middleware = new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };
    $request = Request::create('/settings/profile', 'PATCH', ['name' => 'x', '_token' => 'not-the-token'], server: $server);
    $request->setLaravelSession(app('session.store'));

    expect(fn () => $middleware->handle($request, fn () => response('ok')))->toThrow(TokenMismatchException::class);
})->with([
    'no Sec-Fetch-Site header' => [[]],
    'same-site' => [['HTTP_SEC_FETCH_SITE' => 'same-site']],
]);

it('lets only a sandboxed, default-deny policy replace the page policy', function () {
    Route::middleware('web')->get('/_test/weak-csp', fn () => response('x')->header('Content-Security-Policy', 'default-src *'));

    expect((string) $this->get('/_test/weak-csp')->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

describe('output encoding', function () {
    it('uses no raw Blade output in application templates', function () {
        $raw = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)))
            ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
            ->filter(fn (SplFileInfo $file): bool => str_contains((string) file_get_contents($file->getPathname()), '{!!'))
            ->map(fn (SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();

        expect($raw)->toBe([]);
    });
});

describe('secrets at rest', function () {
    /**
     * Columns whose names suggest a secret. Each is hashed, encrypted, or
     * listed here with the reason it is safe.
     */
    $reviewed = [
        'users.password' => 'hashed (Argon2id) by the model cast',
        'users.remember_token' => 'random token compared by the framework; renewed when sessions end',
        'users.two_factor_secret' => 'encrypted with the application key by the sign-in package',
        'users.two_factor_recovery_codes' => 'hashes of the codes (S-0904)',
        'api_tokens.token' => 'SHA-256 of the token secret; the secret is shown once (S-0913)',
        'api_tokens.tokenable_type' => 'the owner of a token, not a secret',
        'api_tokens.tokenable_id' => 'the owner of a token, not a secret',
        'shared_links.token' => 'SHA-256 of the link token; the token is shown once (S-0987)',
        'shared_links.password' => 'hashed (Argon2id) by SharedLinks (S-0987)',
    ];

    it('stores no secret-looking column unprotected', function () use ($reviewed) {
        $found = [];

        foreach (Schema::getTables() as $table) {
            foreach (Schema::getColumnListing($table['name']) as $column) {
                if (preg_match('/(secret|password|passwd|token|api_key|private_key|iban|salary)/i', $column) === 1) {
                    $found[] = "{$table['name']}.{$column}";
                }
            }
        }

        // Framework tables: reset tokens are stored hashed, sessions hold no secrets in clear.
        $framework = ['password_reset_tokens.token'];

        expect(array_values(array_diff($found, array_keys($reviewed), $framework)))->toBe([]);
    });

    it('keeps the encryption key out of the database', function () {
        $dump = collect(Schema::getTables())
            ->flatMap(fn (array $table) => DB::table($table['name'])->get()->map(fn ($row) => json_encode($row)))
            ->implode("\n");

        expect($dump)->not->toContain(substr((string) config('app.key'), 7, 20));
    });
});

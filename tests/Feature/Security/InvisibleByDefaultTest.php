<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Middleware\SetSecurityHeaders;
use App\Models\User;

/*
 * S-0985: every response is unindexable and leaks no referrer, whatever its
 * status code, and robots.txt disallows everything.
 */

dataset('responses', [
    'login page' => fn () => test()->get('/login'),
    'redirect from root' => fn () => test()->get('/'),
    'redirect to login' => fn () => test()->get('/dashboard'),
    'unknown url (404)' => fn () => test()->get('/does-not-exist'),
    'json error' => fn () => test()->getJson('/does-not-exist'),
    'health check' => fn () => test()->get('/up'),
    'signed-in page' => fn () => test()->actingAs(User::factory()->create())->get('/dashboard'),
]);

it('marks every response as not indexable and without referrer', function ($response) {
    $response->assertHeader('X-Robots-Tag', SetSecurityHeaders::ROBOTS);
    $response->assertHeader('Referrer-Policy', 'no-referrer');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeaderMissing('X-Powered-By');

    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->not->toContain('unsafe-eval');
})->with('responses');

it('adds a robots meta tag and nonce-protected inline code to HTML pages', function () {
    $response = $this->get('/login')->assertOk();
    $html = (string) $response->getContent();

    expect($html)->toContain('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">');

    preg_match('/script-src[^;]*\'nonce-([^\']+)\'/', (string) $response->headers->get('Content-Security-Policy'), $match);
    expect($match)->toHaveKey(1);

    // Every executable script and every style element carries the response nonce.
    // JSON data blocks (the Inertia page payload) are never executed and are exempt.
    preg_match_all('/<(script|style)\b[^>]*>/i', $html, $tags);
    $executable = array_filter($tags[0], fn (string $tag): bool => ! str_contains($tag, 'type="application/json"'));

    expect($executable)->not->toBeEmpty();
    foreach ($executable as $tag) {
        expect($tag)->toContain('nonce="'.$match[1].'"');
    }
});

it('serves no sitemap, social preview or structured data', function () {
    $html = (string) $this->get('/login')->getContent();

    expect($html)
        ->not->toContain('og:')
        ->not->toContain('twitter:')
        ->not->toContain('schema.org');

    $this->get('/sitemap.xml')->assertNotFound();
});

it('disallows every path in robots.txt', function () {
    $robots = (string) file_get_contents(public_path('robots.txt'));

    expect($robots)->toMatch('/^User-agent: \*\s*$/m')
        ->and($robots)->toMatch('/^Disallow: \/\s*$/m')
        ->and($robots)->not->toMatch('/^Allow:/m');
});

it('loads no resource from a third-party host on the login page', function () {
    $html = (string) $this->get('/login')->getContent();

    preg_match_all('/\b(?:src|href)="(?:https?:)?\/\/([^"\/:]+)/i', $html, $hosts);
    $own = parse_url((string) config('app.url'), PHP_URL_HOST);

    foreach ($hosts[1] as $host) {
        expect($host)->toBe($own);
    }
});

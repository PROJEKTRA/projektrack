/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { expect, test } from '@playwright/test';
import type { APIResponse, Page, Response } from '@playwright/test';
import { BASE_URL } from '../../playwright.config';
import { signIn } from './support';

/*
 * CI crawl (S-0988, security baseline 6.2): a running instance, anonymous
 * and signed in. Every response, every rendered page and every browser
 * request is checked against the invisible-by-default rules; any finding
 * fails the build and is listed in the JUnit report.
 */

const HOST = new URL(BASE_URL).host;

// A crawl visits many pages; the default 30 s covers one flow.
test.describe.configure({ timeout: 180_000 });

const ROBOTS = [
    'noindex',
    'nofollow',
    'noarchive',
    'nosnippet',
    'noimageindex',
    'notranslate',
];
const FINGERPRINTS = [
    /Projektrack/i,
    /PROJEKTRA/,
    /Laravel/i,
    /Symfony/i,
    /\bVite\b/,
    /\bInertia\b/,
    // A version number, not an IPv4 address such as the app URL.
    /(?<![\d.])\d+\.\d+\.\d+(?!\.?\d)/,
];
const INDEXABLE = [
    /property="og:/i,
    /name="twitter:/i,
    /application\/ld\+json/i,
    /itemscope/i,
    /name="generator"/i,
    /sitemap/i,
    /opensearch/i,
];
const LEAKS = [/Stack trace/, /#0 \//, /\.php:\d+/, /vendor\//, /SQLSTATE/];

type Header = Record<string, string>;

/** Header rules every response must keep (rules 1 and 2). */
function headerProblems(url: string, headers: Header, type: string): string[] {
    const problems: string[] = [];
    const robots = (headers['x-robots-tag'] ?? '')
        .split(',')
        .map((part) => part.trim());

    for (const directive of ROBOTS) {
        if (!robots.includes(directive)) {
            problems.push(`${url}: X-Robots-Tag lacks ${directive}`);
        }
    }
    if (headers['referrer-policy'] !== 'no-referrer') {
        problems.push(`${url}: Referrer-Policy is not no-referrer`);
    }
    if (headers['x-content-type-options'] !== 'nosniff') {
        problems.push(`${url}: X-Content-Type-Options is not nosniff`);
    }
    if (headers['x-powered-by'] !== undefined) {
        problems.push(`${url}: X-Powered-By present`);
    }
    if (/\d/.test(headers['server'] ?? '')) {
        problems.push(`${url}: Server header has a version`);
    }
    if (type.includes('text/html')) {
        const csp = headers['content-security-policy'] ?? '';
        if (!csp.includes("frame-ancestors 'none'")) {
            problems.push(`${url}: CSP without frame-ancestors 'none'`);
        }
        // Every directive that governs scripts, script-src-elem and -attr too.
        const scripts = csp
            .split(';')
            .map((d) => d.trim())
            .filter((d) => /^(script-src(-elem|-attr)?|default-src)\s/.test(d));
        if (!scripts.some((d) => d.startsWith('script-src '))) {
            problems.push(`${url}: CSP without script-src`);
        }
        if (scripts.some((d) => /unsafe-inline|unsafe-eval/.test(d))) {
            problems.push(`${url}: CSP allows unsafe scripts`);
        }
    }

    return problems;
}

/** Body rules (4, 5, 9) on what the server sent. */
function bodyProblems(
    url: string,
    status: number,
    type: string,
    body: string,
    anonymous: boolean,
): string[] {
    const problems: string[] = [];

    if (type.includes('text/html')) {
        if (!/<meta name="robots" content="noindex/.test(body)) {
            problems.push(`${url}: HTML without robots meta`);
        }
        for (const pattern of INDEXABLE) {
            if (pattern.test(body)) {
                problems.push(`${url}: indexable markup ${pattern}`);
            }
        }
    }

    // Scripts and styles are the client itself (accepted, security baseline 6.2).
    if (anonymous && !/javascript|css|svg|image|font/.test(type)) {
        for (const pattern of FINGERPRINTS) {
            const match = pattern.exec(body);
            if (match) {
                problems.push(`${url}: anonymous response shows "${match[0]}"`);
            }
        }
    }

    if (status >= 400) {
        for (const pattern of LEAKS) {
            if (pattern.test(body)) {
                problems.push(`${url}: error response leaks ${pattern}`);
            }
        }
    }

    return problems;
}

/** Rules 4 and 8 on the rendered page: Vue adds links and head tags. */
async function domProblems(page: Page): Promise<string[]> {
    return page.evaluate((host) => {
        const problems: string[] = [];
        const where = location.pathname;

        for (const anchor of Array.from(document.querySelectorAll('a[href]'))) {
            const url = new URL((anchor as HTMLAnchorElement).href);
            const rel = (anchor.getAttribute('rel') ?? '').split(/\s+/);
            if (
                /^https?:$/.test(url.protocol) &&
                url.host !== host &&
                !(rel.includes('noopener') && rel.includes('noreferrer'))
            ) {
                problems.push(
                    `${where}: link to ${url.host} without noopener noreferrer`,
                );
            }
        }

        const indexable = document.querySelectorAll(
            'meta[property^="og:"], meta[name^="twitter:"], meta[name="generator"], script[type="application/ld+json"], [itemscope], link[rel="sitemap"], link[rel="search"], link[rel="dns-prefetch"], link[rel="preconnect"]',
        );
        if (indexable.length > 0) {
            problems.push(
                `${where}: ${indexable.length} indexable or prefetch tags`,
            );
        }
        if (
            !document.querySelector('meta[name="robots"][content^="noindex"]')
        ) {
            problems.push(`${where}: rendered page without robots meta`);
        }

        return problems;
    }, HOST);
}

/** Records every response and every foreign request of a page (rule 6). */
function watch(page: Page, anonymous: boolean) {
    const problems: string[] = [];
    const pending: Promise<void>[] = [];

    page.on('request', (request) => {
        const url = request.url();
        if (!/^(data|blob):/.test(url) && new URL(url).host !== HOST) {
            problems.push(`request to another host: ${url}`);
        }
    });

    page.on('response', (response: Response) => {
        if (new URL(response.url()).host !== HOST) {
            return;
        }
        const headers = response.headers();
        const type = headers['content-type'] ?? '';
        problems.push(...headerProblems(response.url(), headers, type));

        // Redirects have no body to read.
        if (response.status() >= 300 && response.status() < 400) {
            return;
        }
        const resource = response.request().resourceType();
        pending.push(
            response.text().then(
                (body) => {
                    problems.push(
                        ...bodyProblems(
                            response.url(),
                            response.status(),
                            type,
                            body,
                            anonymous,
                        ),
                    );
                },
                () => {
                    // A page or data response whose body is gone was not checked.
                    if (['document', 'xhr', 'fetch'].includes(resource)) {
                        problems.push(
                            `${response.url()}: body could not be checked`,
                        );
                    }
                },
            ),
        );
    });

    return {
        problems,
        /** Visit a path, wait for its requests and check the rendered page. */
        async visit(path: string): Promise<void> {
            const response = await page.goto(path);
            // The body of the page itself, before anything navigates away.
            await response?.finished();
            await page.waitForLoadState('networkidle');
            problems.push(...(await domProblems(page)));
            await Promise.all(pending);
        },
        async settled(): Promise<string[]> {
            await Promise.all(pending);

            return problems;
        },
    };
}

async function checkDirect(
    response: APIResponse,
    anonymous: boolean,
): Promise<string[]> {
    const headers = response.headers();
    const type = headers['content-type'] ?? '';

    return [
        ...headerProblems(response.url(), headers, type),
        ...bodyProblems(
            response.url(),
            response.status(),
            type,
            await response.text(),
            anonymous,
        ),
    ];
}

test('anonymous visitors see nothing indexable or telling', async ({
    page,
    request,
}) => {
    const watcher = watch(page, true);

    for (const path of [
        '/',
        '/login',
        '/forgot-password',
        '/reset-password/not-a-token',
        '/does-not-exist',
    ]) {
        await watcher.visit(path);
    }

    const direct: string[] = [];
    // Rule 3: robots.txt is exactly disallow-all.
    const robots = await request.get('/robots.txt');
    expect((await robots.text()).replace(/\r\n/g, '\n').trim()).toBe(
        'User-agent: *\nDisallow: /',
    );
    direct.push(...(await checkDirect(robots, true)));

    // INV-10: the health check says OK and nothing else.
    const health = await request.get('/health');
    expect(await health.text()).toBe('OK');
    direct.push(...(await checkDirect(health, true)));

    for (const path of [
        '/favicon.svg',
        '/api/v1/me',
        '/api/v1/nothing',
        '/sitemap.xml',
        '/.well-known/security.txt',
        '/.env',
        '/composer.json',
        '/storage/logs/laravel.log',
    ]) {
        const response = await request.get(path, {
            headers: { Accept: 'application/json' },
        });
        direct.push(...(await checkDirect(response, true)));

        if (!['/favicon.svg', '/api/v1/me'].includes(path)) {
            expect(response.status(), path).not.toBe(200);
        }
    }

    // 405 and OPTIONS look like an unknown path; 419 without a token.
    const options = await request.fetch('/login', { method: 'OPTIONS' });
    expect(options.status()).toBe(404);
    expect(options.headers()['allow']).toBeUndefined();
    direct.push(...(await checkDirect(options, true)));
    direct.push(...(await checkDirect(await request.delete('/login'), true)));
    const expired = await request.post('/login', {
        form: { email: 'x@example.com', password: 'x' },
    });
    expect(expired.status()).toBe(419);
    direct.push(...(await checkDirect(expired, true)));

    expect([...(await watcher.settled()), ...direct]).toEqual([]);
});

test('signed-in pages keep every rule', async ({ page }) => {
    const watcher = watch(page, false);
    await signIn(page, 'crawl');

    // Follow same-origin links three levels deep, never sign out.
    const seen = new Set<string>();
    let frontier = ['/dashboard'];

    for (let depth = 0; depth < 3; depth++) {
        const next: string[] = [];

        for (const path of frontier) {
            if (seen.has(path)) {
                continue;
            }
            seen.add(path);

            // Downloads and images are no pages: check the response itself.
            if (/^\/(files|brand)\//.test(path)) {
                watcher.problems.push(
                    ...(await checkDirect(await page.request.get(path), false)),
                );
                continue;
            }
            await watcher.visit(path);

            const links = await page
                .locator('a[href]')
                .evaluateAll((anchors) =>
                    anchors.map((a) => (a as HTMLAnchorElement).href),
                );
            for (const href of links) {
                const url = new URL(href);
                if (url.host === HOST && !/logout/.test(url.pathname)) {
                    next.push(url.pathname + url.search);
                }
            }
        }

        frontier = next;
    }

    expect(seen.size).toBeGreaterThan(5);
    expect(await watcher.settled()).toEqual([]);
});

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { execFileSync, spawn } from 'node:child_process';
import {
    closeSync,
    existsSync,
    openSync,
    readFileSync,
    statSync,
} from 'node:fs';
import { createServer, request } from 'node:http';
import { join, normalize, resolve } from 'node:path';

/*
 * Web server of the browser tests (playwright.config.ts): a fresh SQLite
 * database with the synthetic users of database/seeders/E2eSeeder.php, then
 * PHP's built-in server with Laravel's router script. Playwright starts the
 * server before any global setup, so seeding happens here.
 *
 * A small proxy in front adds the headers public/.htaccess sets on Apache to
 * static files, so the crawl (S-0988) sees what a visitor of a real
 * installation sees. Responses of the application pass through untouched.
 *
 *   node tests/e2e/serve.ts <port>
 */

const port = Number(process.argv[2] ?? '8123');
const phpPort = port + 1;
const database = process.env.DB_DATABASE;
const publicDir = resolve('public');

// The "Header always set" lines of public/.htaccess, read from the file itself,
// so a change there shows in the crawl.
const STATIC_HEADERS: Record<string, string> = Object.fromEntries(
    [
        ...readFileSync(join(publicDir, '.htaccess'), 'utf8').matchAll(
            /^\s*Header\s+always\s+set\s+(\S+)\s+"([^"]*)"/gim,
        ),
    ].map((match) => [match[1].toLowerCase(), match[2]]),
);

if (!database) {
    throw new Error('DB_DATABASE must name the browser test database.');
}

if ((process.env.DB_CONNECTION ?? 'sqlite') === 'sqlite') {
    closeSync(openSync(database, 'w'));
}

execFileSync(
    'php',
    [
        'artisan',
        'migrate:fresh',
        `--seeder=${process.env.E2E_SEEDER ?? 'E2eSeeder'}`,
        '--force',
        '--no-interaction',
    ],
    { stdio: 'inherit' },
);

// The router script takes the current directory as the public directory.
const php = spawn(
    'php',
    [
        '-S',
        `127.0.0.1:${phpPort}`,
        '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
    ],
    { cwd: 'public', stdio: 'inherit' },
);

function isStaticFile(url: string): boolean {
    let path: string;
    try {
        path = decodeURIComponent(new URL(url, 'http://x').pathname);
    } catch {
        // A malformed path is no file; the application answers it.
        return false;
    }
    const file = normalize(join(publicDir, path));

    return (
        path !== '/' &&
        file.startsWith(publicDir) &&
        !file.endsWith('.php') &&
        existsSync(file) &&
        statSync(file).isFile()
    );
}

const proxy = createServer((incoming, outgoing) => {
    const upstream = request(
        {
            host: '127.0.0.1',
            port: phpPort,
            method: incoming.method,
            path: incoming.url,
            headers: incoming.headers,
        },
        (response) => {
            const headers = { ...response.headers };
            if (isStaticFile(incoming.url ?? '/')) {
                Object.assign(headers, STATIC_HEADERS);
                delete headers['x-powered-by'];
            }
            outgoing.writeHead(response.statusCode ?? 502, headers);
            response.pipe(outgoing);
        },
    );
    upstream.on('error', () => {
        if (outgoing.headersSent) {
            outgoing.destroy();

            return;
        }
        outgoing.writeHead(502);
        outgoing.end();
    });
    incoming.pipe(upstream);
});

proxy.listen(port, '127.0.0.1');

for (const signal of ['SIGINT', 'SIGTERM'] as const) {
    process.on(signal, () => php.kill(signal));
}

php.on('exit', (code) => process.exit(code ?? 0));

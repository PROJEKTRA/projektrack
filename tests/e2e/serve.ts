/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { execFileSync, spawn } from 'node:child_process';
import { closeSync, openSync } from 'node:fs';

/*
 * Web server of the browser tests (playwright.config.ts): a fresh SQLite
 * database with the synthetic users of database/seeders/E2eSeeder.php, then
 * PHP's built-in server with Laravel's router script. Playwright starts the
 * server before any global setup, so seeding happens here.
 *
 *   node tests/e2e/serve.ts <port>
 */

const port = process.argv[2] ?? '8123';
const database = process.env.DB_DATABASE;

if (!database) {
    throw new Error('DB_DATABASE must name the browser test database.');
}

closeSync(openSync(database, 'w'));

execFileSync(
    'php',
    [
        'artisan',
        'migrate:fresh',
        '--seeder=E2eSeeder',
        '--force',
        '--no-interaction',
    ],
    { stdio: 'inherit' },
);

// The router script takes the current directory as the public directory.
const server = spawn(
    'php',
    [
        '-S',
        `127.0.0.1:${port}`,
        '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
    ],
    { cwd: 'public', stdio: 'inherit' },
);

for (const signal of ['SIGINT', 'SIGTERM'] as const) {
    process.on(signal, () => server.kill(signal));
}

server.on('exit', (code) => process.exit(code ?? 0));

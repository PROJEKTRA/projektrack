/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { resolve } from 'node:path';
import { defineConfig, devices } from '@playwright/test';

/*
 * Browser tests (S-0920): `npx playwright test` after `npm run build`.
 * The application runs on PHP's built-in server with its own SQLite database,
 * seeded with synthetic users by database/seeders/E2eSeeder.php.
 */

const PORT = 8123;
export const BASE_URL = `http://127.0.0.1:${PORT}`;

// Passed to the seeder (global setup) and to the server, so both use the same
// database and tenant host, never the database of the .env file.
export const E2E_ENV: Record<string, string> = {
    APP_ENV: 'local',
    APP_URL: BASE_URL,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: resolve('database/e2e.sqlite'),
    SESSION_DRIVER: 'database',
    CACHE_STORE: 'database',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    // Several requests at once (Linux and macOS; ignored on Windows).
    PHP_CLI_SERVER_WORKERS: '4',
};

export default defineConfig({
    testDir: 'tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    // Screenshots are compared with baselines rendered on Linux (CI).
    snapshotPathTemplate: '{testDir}/__screenshots__/{arg}-{projectName}{ext}',
    expect: {
        toHaveScreenshot: { maxDiffPixelRatio: 0.01 },
    },
    use: {
        baseURL: BASE_URL,
        testIdAttribute: 'data-test',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        locale: 'en-GB',
        timezoneId: 'UTC',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
            },
        },
    ],
    webServer: {
        // Laravel's router script, as `php artisan serve` runs it (which would
        // drop the environment below).
        command: `php -S 127.0.0.1:${PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: 'public',
        url: `${BASE_URL}/login`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
        env: E2E_ENV,
    },
});

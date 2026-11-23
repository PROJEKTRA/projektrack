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

// Passed to the seeder and to the server (tests/e2e/serve.ts), so both use the same
// database and tenant host, never the database of the .env file.
export const E2E_ENV: Record<string, string> = {
    APP_ENV: 'local',
    // Error pages as on a production server, so the crawl (S-0988) checks those.
    APP_DEBUG: 'false',
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
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 0,
    // JUnit report kept as a CI artifact (S-0988).
    reporter: process.env.CI
        ? [
              ['github'],
              ['list'],
              ['junit', { outputFile: 'test-results/junit.xml' }],
          ]
        : 'list',
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
        // Seeds a fresh database, then runs PHP's built-in server.
        command: `node tests/e2e/serve.ts ${PORT}`,
        url: `${BASE_URL}/login`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
        env: E2E_ENV,
    },
});

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import base, { E2E_ENV } from './playwright.config';

/*
 * Performance test: `npx playwright test -c playwright.performance.config.ts`.
 * The browser test server with the data of database/seeders/PerformanceSeeder.php.
 * The database can be chosen with DB_CONNECTION (default SQLite).
 */
const env: Record<string, string> = {
    ...E2E_ENV,
    E2E_SEEDER: 'PerformanceSeeder',
};

if (process.env.DB_CONNECTION && process.env.DB_CONNECTION !== 'sqlite') {
    delete env.DB_CONNECTION;
    delete env.DB_DATABASE;
}

export default {
    ...base,
    testDir: 'tests/performance',
    timeout: 300_000,
    webServer: {
        ...base.webServer,
        reuseExistingServer: false,
        timeout: 300_000,
        env,
    },
};

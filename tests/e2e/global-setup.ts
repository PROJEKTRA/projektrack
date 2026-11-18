/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { execFileSync } from 'node:child_process';
import { closeSync, openSync } from 'node:fs';
import { E2E_ENV } from '../../playwright.config';

/** A fresh database with the synthetic users of database/seeders/E2eSeeder.php. */
export default function globalSetup(): void {
    closeSync(openSync(E2E_ENV.DB_DATABASE, 'w'));

    execFileSync(
        'php',
        [
            'artisan',
            'migrate:fresh',
            '--seeder=E2eSeeder',
            '--force',
            '--no-interaction',
        ],
        { env: { ...process.env, ...E2E_ENV }, stdio: 'inherit' },
    );
}

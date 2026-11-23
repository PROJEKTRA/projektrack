/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { createHmac } from 'node:crypto';
import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/** Synthetic users of database/seeders/E2eSeeder.php. */
export const PASSWORD = 'e2e-password-not-secret';
export const USERS = {
    bs: {
        email: 'e2e-bs@example.test',
        secret: 'JBSWY3DPEHPK3PXP',
        locale: 'bs',
    },
    ar: {
        email: 'e2e-ar@example.test',
        secret: 'GEZDGNBVGY3TQOJQ',
        locale: 'ar',
    },
    // The crawl's own user: a code is accepted once, tests run in parallel.
    crawl: {
        email: 'e2e-crawl@example.test',
        secret: 'KRSXG5DDOJQXOZBA',
        locale: 'bs',
    },
    crm: {
        email: 'e2e-crm@example.test',
        secret: 'MFRGGZDFMZTWQ2LK',
        locale: 'bs',
    },
    projects: {
        email: 'e2e-projects@example.test',
        secret: 'NBSWY3DPEHPK3PXA',
        locale: 'bs',
    },
    time: {
        email: 'e2e-time@example.test',
        secret: 'ONSWG4TFOQQHI33M',
        locale: 'bs',
    },
    invoices: {
        email: 'e2e-invoices@example.test',
        secret: 'PFXXK4RAONSWG4TF',
        locale: 'bs',
    },
} as const;

export type TestUser = keyof typeof USERS;
export type TestLocale = 'bs' | 'ar';

function base32(secret: string): Buffer {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';

    for (const char of secret.replace(/=+$/, '').toUpperCase()) {
        bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    }

    const bytes = bits.match(/.{8}/g) ?? [];

    return Buffer.from(bytes.map((byte) => parseInt(byte, 2)));
}

/** RFC 6238 code (SHA-1, 6 digits, 30 seconds), as an authenticator app shows it. */
export function totp(secret: string, now = Date.now()): string {
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(now / 1000 / 30)));

    const hmac = createHmac('sha1', base32(secret)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0x0f;
    const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return code.toString().padStart(6, '0');
}

/** Sign in with password and authenticator code; ends on the dashboard. */
export async function signIn(page: Page, who: TestUser): Promise<void> {
    const user = USERS[who];

    await page.goto('/login');
    await page.locator('#email').fill(user.email);
    await page.locator('#password').fill(PASSWORD);
    await page.getByTestId('login-button').click();

    await page.waitForURL('**/two-factor-challenge');
    await page.locator('#otp').pressSequentially(totp(user.secret));
    await page.locator('form button[type="submit"]').first().click();

    await page.waitForURL('**/dashboard');
    // The string bundle of the user's language follows the first page.
    await page.waitForLoadState('networkidle');
    await expect(page.locator('html')).toHaveAttribute('lang', user.locale);
}

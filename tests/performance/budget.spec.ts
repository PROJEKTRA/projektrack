/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import type { APIRequestContext } from '@playwright/test';
import { PASSWORD, signIn } from '../e2e/support';

/*
 * Performance budget with 100,000 time entries in one tenant: the server
 * answers a page within 300 ms (p95), and ten people working at once (far
 * more than 50 users cause) wait at most a second and see no error.
 */

const PAGE_BUDGET = 300;
const LOAD_BUDGET = 1000;
const ROUNDS = 20;
const CLIENTS = 10;

const PAGES = [
    '/dashboard',
    '/crm/companies',
    '/crm/companies?search=Customer%201',
    '/projects',
    '/timesheets',
    '/timesheets/approvals',
    '/timesheets/periods',
    '/rates',
    '/rates/margin',
    '/service-reports',
    '/invoices',
    '/invoices?show=open',
    '/invoices?page=40',
    '/admin/users',
    '/notifications',
];

type Result = { path: string; p50: number; p95: number; max: number };

function summary(path: string, times: number[]): Result {
    const sorted = [...times].sort((a, b) => a - b);
    const at = (share: number) =>
        Math.round(sorted[Math.ceil(sorted.length * share) - 1]);

    return { path, p50: at(0.5), p95: at(0.95), max: at(1) };
}

async function timed(request: APIRequestContext, path: string) {
    const start = performance.now();
    const response = await request.get(path, { maxRedirects: 0 });
    await response.body();

    return { ms: performance.now() - start, status: response.status() };
}

test('stays within the performance budget', async ({ page }) => {
    await signIn(page, 'bs');
    // User administration asks for the password again.
    await page.goto('/admin/users');
    await page.locator('#password').fill(PASSWORD);
    await page.getByTestId('confirm-password-button').click();
    await page.waitForURL('**/admin/users');
    await page.goto('/projects');
    const project = await page
        .locator('a[href*="/projects/"]')
        .first()
        .getAttribute('href');
    const request = page.request;
    const paths = [...PAGES, new URL(project ?? '', page.url()).pathname];

    const single: Result[] = [];
    for (const path of paths) {
        // The first request warms the caches, as any running installation has.
        expect((await timed(request, path)).status, path).toBe(200);
        const times: number[] = [];
        for (let i = 0; i < ROUNDS; i++) {
            const { ms, status } = await timed(request, path);
            expect(status, path).toBe(200);
            times.push(ms);
        }
        single.push(summary(path, times));
    }

    const failures: string[] = [];
    const times: number[] = [];
    await Promise.all(
        Array.from({ length: CLIENTS }, async (_, client) => {
            for (let i = 0; i < ROUNDS; i++) {
                const path = paths[(client * 3 + i) % paths.length];
                const { ms, status } = await timed(request, path);
                times.push(ms);
                if (status !== 200) {
                    failures.push(`${path}: ${status}`);
                }
            }
        }),
    );
    const load = summary(`${CLIENTS} at once`, times);

    console.table([...single, load]);
    mkdirSync('test-results', { recursive: true });
    writeFileSync(
        'test-results/performance.json',
        JSON.stringify({ single, load, failures }, null, 2),
    );

    expect(failures).toEqual([]);
    expect(single.filter((r) => r.p95 > PAGE_BUDGET)).toEqual([]);
    expect(load.p95).toBeLessThanOrEqual(LOAD_BUDGET);
});

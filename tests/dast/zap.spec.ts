/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { spawnSync } from 'node:child_process';
import {
    chmodSync,
    copyFileSync,
    mkdirSync,
    readFileSync,
    writeFileSync,
} from 'node:fs';
import { resolve } from 'node:path';
import { expect, test } from '@playwright/test';
import { BASE_URL } from '../../playwright.config';
import { signIn } from '../e2e/support';

// ZAP 2.17.0
const IMAGE =
    'zaproxy/zap-stable@sha256:9624669db72f54d86c4b46a7064518bd9bf79d8c61a4c31befa9923489f328af';

type Alert = {
    name: string;
    riskcode: string;
    count: string;
    instances: { method: string; uri: string; param: string }[];
};

/** Passive and active scan; returns what is medium or worse. */
function scan(name: string, cookie: string, urls: string[]): string[] {
    const dir = resolve(`test-results/zap-${name}`);
    mkdirSync(dir, { recursive: true });
    // The container runs as another user.
    chmodSync(dir, 0o777);
    copyFileSync('tests/dast/zap_hook.py', `${dir}/hook.py`);
    writeFileSync(`${dir}/scan.json`, JSON.stringify({ cookie, urls }));

    const run = spawnSync(
        'docker',
        [
            ...['run', '--rm', '--network', 'host', '-v', `${dir}:/zap/wrk:rw`],
            ...[IMAGE, 'zap-full-scan.py', '-t', `${BASE_URL}/login`],
            ...['-J', 'report.json', '--hook', '/zap/wrk/hook.py'],
            ...['-m', '2', '-T', '12', '-I'],
        ],
        { encoding: 'utf8', timeout: 1_500_000 },
    );
    console.log(run.stdout?.slice(-6000), run.stderr?.slice(-2000));
    expect(run.status, 'the scan ran').toBe(0);

    const report = JSON.parse(readFileSync(`${dir}/report.json`, 'utf8')) as {
        site: { alerts: Alert[] }[];
    };
    expect(report.site.length, 'something was scanned').toBeGreaterThan(0);

    return report.site
        .flatMap((site) => site.alerts)
        .filter((alert) => Number(alert.riskcode) >= 2)
        .map(
            (alert) =>
                `${alert.name} (${alert.count}): ${alert.instances
                    .slice(0, 5)
                    .map((i) => `${i.method} ${i.uri} ${i.param}`)
                    .join(', ')}`,
        );
}

test('an anonymous attacker finds nothing', async () => {
    expect(
        scan('anonymous', '', [
            `${BASE_URL}/`,
            `${BASE_URL}/forgot-password`,
            `${BASE_URL}/reset-password/token?email=a@example.test`,
            `${BASE_URL}/s/not-a-token`,
            `${BASE_URL}/api/v1/me`,
            `${BASE_URL}/health`,
        ]),
    ).toEqual([]);
});

test('a signed-in attacker finds nothing', async ({ page }) => {
    await signIn(page, 'bs');
    const urls = new Set<string>();
    for (const path of [
        '/dashboard',
        '/crm/companies?search=a&status=active',
        '/projects?status=active',
        '/timesheets?date=2026-11-04',
        '/timesheets/approvals',
        '/timesheets/periods?month=2026-11',
        '/rates',
        '/rates/margin?from=2026-10-01&to=2026-10-31',
        '/service-reports',
        '/invoices?show=open',
        '/admin/users',
        '/admin/settings',
        '/admin/languages',
        '/admin/brand',
        '/notifications',
        '/settings/profile',
    ]) {
        await page.goto(path);
        urls.add(`${BASE_URL}${path}`);
        for (const href of await page
            .locator('a[href]')
            .evaluateAll((all) =>
                all.map((a) => (a as HTMLAnchorElement).href),
            )) {
            if (href.startsWith(BASE_URL) && !href.includes('logout')) {
                urls.add(href);
            }
        }
    }
    const cookie = (await page.context().cookies())
        .map((c) => `${c.name}=${c.value}`)
        .join('; ');

    const found = scan('signed-in', cookie, [...urls]);
    // The session survived, so the scan saw the pages and not the login.
    const still = await page.request.get('/dashboard', { maxRedirects: 0 });
    expect(still.status(), 'still signed in').toBe(200);
    expect(found).toEqual([]);
});

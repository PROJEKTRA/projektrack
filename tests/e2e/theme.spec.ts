/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

/*
 * S-0910: the theme in a real browser (sign-in page): self-hosted fonts,
 * dark mode, reduced motion, and a tenant theme that wins over the default.
 * Contrast is checked in resources/js/theme/tokens.test.ts.
 */

async function style(
    page: Page,
    selector: string,
    property: string,
): Promise<string> {
    return page
        .locator(selector)
        .first()
        .evaluate(
            (element, name) => getComputedStyle(element).getPropertyValue(name),
            property,
        );
}

test('fonts come from the instance', async ({ page }) => {
    const fonts: string[] = [];
    page.on('response', (response) => {
        if (response.request().resourceType() === 'font' && response.ok()) {
            fonts.push(new URL(response.url()).origin);
        }
    });

    await page.goto('/login', { waitUntil: 'networkidle' });

    expect(fonts.length).toBeGreaterThan(0);
    expect(new Set(fonts)).toEqual(new Set([new URL(page.url()).origin]));

    const loaded = await page.evaluate(() =>
        [...document.fonts]
            .filter((font) => font.status === 'loaded')
            .map((font) => font.family.replaceAll('"', '')),
    );
    expect(loaded).toContain('Golos Text Variable');
    expect(loaded).toContain('Mona Sans Variable');
});

test('light and dark use their tokens', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'light' });
    await page.goto('/login');
    expect(await style(page, 'body', 'background-color')).toBe(
        'rgb(245, 247, 247)',
    );

    await page.emulateMedia({ colorScheme: 'dark' });
    await page.reload();
    expect(await style(page, 'body', 'background-color')).toBe('rgb(0, 0, 18)');
    expect(await style(page, 'body', 'color')).toBe('rgb(230, 236, 245)');
});

test('a tenant color in the page head wins over the default theme', async ({
    page,
}) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/login');

    // Where the brand block stands (S-0911): before the stylesheet.
    await page.evaluate(() => {
        const head = document.querySelector('head style') as HTMLStyleElement;
        head.sheet?.insertRule(':root{--primary:#1d7a3a;}', 0);
    });

    await expect
        .poll(() =>
            style(page, '[data-test="login-button"]', 'background-color'),
        )
        .toBe('rgb(29, 122, 58)');
});

test('reduced motion stops transitions', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/login');

    expect(
        parseFloat(
            await style(
                page,
                '[data-test="login-button"]',
                'transition-duration',
            ),
        ),
    ).toBeLessThan(0.001);
});

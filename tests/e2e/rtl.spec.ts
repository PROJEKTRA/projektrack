/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { signIn } from './support';
import type { TestLocale } from './support';

/*
 * S-0920: the interface is mirrored in right to left languages (ar) and not
 * in left to right ones (bs). Layout checks run everywhere; screenshots are
 * compared on Linux only, where the baselines are rendered (CI).
 */

const MIRRORED = 'matrix(-1, 0, 0, 1, 0, 0)';
const screenshots = process.platform === 'linux';

async function transformOf(page: Page, selector: string): Promise<string> {
    return page
        .locator(selector)
        .first()
        .evaluate((element) => getComputedStyle(element).transform);
}

async function snapshot(page: Page, name: string): Promise<void> {
    if (screenshots) {
        await expect(page).toHaveScreenshot(`${name}.png`, { fullPage: true });
    }
}

test('sign-in page before sign-in (bs)', async ({ page }) => {
    await page.goto('/login');

    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await snapshot(page, 'login-bs');
});

for (const locale of ['bs', 'ar'] as TestLocale[]) {
    const dir = locale === 'ar' ? 'rtl' : 'ltr';

    test(`app shell and admin screens (${locale}, ${dir})`, async ({
        page,
    }) => {
        await signIn(page, locale);

        // dir and lang of the user's language
        const html = page.locator('html');
        await expect(html).toHaveAttribute('dir', dir);
        await expect(html).toHaveAttribute('lang', locale);

        // The sidebar sits at the start of the reading direction.
        await expect(
            page.locator('[data-slot="sidebar"][data-side]'),
        ).toHaveAttribute('data-side', dir === 'rtl' ? 'right' : 'left');
        const sidebar = await page
            .locator(
                '[data-slot="sidebar"][data-side] [data-sidebar="sidebar"]',
            )
            .boundingBox();
        expect(sidebar).not.toBeNull();
        const viewport = page.viewportSize()!;
        if (dir === 'rtl') {
            expect(sidebar!.x).toBeGreaterThan(viewport.width / 2);
        } else {
            expect(sidebar!.x + sidebar!.width).toBeLessThan(
                viewport.width / 2,
            );
        }

        // Directional icons mirror, icons without a direction never do.
        expect(await transformOf(page, '[data-sidebar="trigger"] svg')).toBe(
            dir === 'rtl' ? MIRRORED : 'none',
        );
        expect(await transformOf(page, '.lucide-chevrons-up-down')).toBe(
            'none',
        );

        await snapshot(page, `dashboard-${locale}`);

        await page.goto('/settings/profile');
        await expect(page.locator('#email')).toHaveAttribute('dir', 'ltr');
        await snapshot(page, `profile-${locale}`);

        await page.goto('/admin/languages');
        await expect(page.getByTestId('language-ar')).toBeVisible();
        await snapshot(page, `languages-${locale}`);

        // Menus follow the direction (Reka UI ConfigProvider).
        await page
            .getByTestId('language-ar')
            .getByRole('button')
            .last()
            .click();
        await expect(page.getByRole('menu')).toHaveAttribute('dir', dir);
        await page.keyboard.press('Escape');

        // Keys and amounts keep their order inside right to left text.
        await page.goto('/admin/languages/ar/strings?q=common.save');
        await expect(
            page.locator('[data-test="string-core:common.save"] bdi').first(),
        ).toHaveText('core:common.save');
    });
}

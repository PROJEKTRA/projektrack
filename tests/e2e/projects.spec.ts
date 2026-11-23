/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { expect, test } from '@playwright/test';
import { signIn } from './support';

// S-0940, S-0942: a project with nested work packages and a contract.
test('creates a project and structures it', async ({ page }) => {
    await signIn(page, 'crawl');
    await page.goto('/projects');
    await page.getByTestId('new-project').click();
    const dialog = page.getByRole('dialog');
    const field = (name: string) => dialog.locator(`[name="${name}"]`);
    await field('name').fill('GG Operations');
    await field('budget_minutes').fill('300');
    await page.getByTestId('dialog-save').click();
    await expect(page).toHaveURL(/\/projects\/[0-9a-z]{26}$/i);

    for (const name of ['Consulting', 'Backend']) {
        await page.getByTestId('new-package').click();
        await field('name').fill(name);
        await page.getByTestId('dialog-save').click();
        await expect(
            page.getByRole('listitem').filter({ hasText: name }),
        ).toBeVisible();
    }

    const backend = page.getByRole('listitem').filter({ hasText: 'Backend' });
    await expect(backend).toContainText('2');
    await backend
        .getByRole('button', { name: 'Pomjeri pod paket iznad' })
        .click();
    await expect(backend).toContainText('1.1');

    await page.getByTestId('new-contract').click();
    await field('number').fill('SOW-2026-03');
    await page.getByTestId('dialog-save').click();
    await expect(page.getByText('SOW-2026-03')).toBeVisible();
});

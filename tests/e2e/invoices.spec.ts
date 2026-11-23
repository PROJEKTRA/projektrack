/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { expect, test } from '@playwright/test';
import { signIn } from './support';

// S-0961 to S-0966: draft, issue, pay. Seller and customer come from E2eSeeder.
test('issues an invoice and records its payment', async ({ page }) => {
    await signIn(page, 'crawl');
    await page.goto('/invoices');
    await page.getByTestId('new-invoice').click();
    const dialog = page.getByRole('dialog');
    const field = (name: string) => dialog.locator(`[name="${name}"]`);
    await dialog.getByRole('button', { name: 'Dodaj stavku' }).click();
    await field('lines.0.description').fill('Savjetovanje');
    await field('lines.0.quantity').fill('2');
    await field('lines.0.unit_price').fill('100,50');
    await page.getByTestId('dialog-save').click();

    await expect(page.getByTestId('gross')).toContainText(/235[.,]17/);
    await page.getByTestId('finalize').click();
    await page.getByTestId('dialog-save').click();
    await expect(
        page.getByRole('heading', { name: /INV-20\d\d-0001/ }),
    ).toBeVisible();

    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'PDF' }).click();
    expect((await download).suggestedFilename()).toMatch(/^INV-.*\.pdf$/);

    await page.getByTestId('pay').click();
    await field('amount').fill('235,17');
    await page.getByTestId('dialog-save').click();
    await expect(page.getByText('Plaćena')).toBeVisible();
});

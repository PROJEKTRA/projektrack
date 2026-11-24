/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { expect, test } from '@playwright/test';
import { signIn } from './support';

// S-0939: a customer with address, tax data and contact in one dialog.
test('creates and edits a customer', async ({ page }) => {
    await signIn(page, 'crm');
    await page.goto('/crm/companies');
    await page.getByTestId('new-company').click();

    const dialog = page.getByRole('dialog');
    const field = (name: string) => dialog.locator(`[name="${name}"]`);
    await field('name').fill('Synthetic GmbH');
    await field('address.line1').fill('Teststr. 1');
    await field('address.city').fill('Stuttgart');
    await field('address.country_code').fill('DE');
    await field('tax.tax_status').selectOption('eu_business');
    await field('tax.country_code').fill('DE');
    await field('tax.vat_id').fill('DE12');
    await field('contacts.0.last_name').fill('Example');
    await field('contacts.0.email').fill('erika@example.test');
    await page.getByTestId('save-company').click();
    await expect(dialog.getByText('PDV broj nema format')).toBeVisible();

    await field('tax.vat_id').fill('DE 123 456 789');
    await page.getByTestId('save-company').click();
    await expect(page).toHaveURL(/\/crm\/companies\/[0-9a-z]{26}$/i);
    await expect(page.getByText('DE123456789')).toBeVisible();
    await expect(page.getByText('erika@example.test')).toBeVisible();

    await page.getByTestId('edit-company').click();
    await field('name').fill('Synthetic AG');
    await page.getByTestId('save-company').click();
    await expect(
        page.getByRole('heading', { name: 'Synthetic AG' }),
    ).toBeVisible();
});

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { expect, test } from '@playwright/test';
import { signIn } from './support';

// S-0945: book a week with the keyboard, undo, submit.
test('books a week with the keyboard', async ({ page }) => {
    await signIn(page, 'time');
    await page.goto('/projects');
    await page.getByTestId('new-project').click();
    await page
        .getByRole('dialog')
        .locator('[name="name"]')
        .fill('Time project');
    await page.getByTestId('dialog-save').click();
    await page.getByTestId('new-package').click();
    await page.getByRole('dialog').locator('[name="name"]').fill('Consulting');
    await page.getByTestId('dialog-save').click();
    await expect(
        page.getByRole('listitem').filter({ hasText: 'Consulting' }),
    ).toBeVisible();

    await page.goto('/timesheets?date=2026-11-04');
    const cell = (date: string) => page.getByTestId(`cell-1-${date}`);
    await cell('2026-11-02').fill('1,5');
    await cell('2026-11-02').press('ArrowRight');
    await expect(cell('2026-11-03')).toBeFocused();
    await cell('2026-11-03').fill('2:15');
    await cell('2026-11-03').press('Tab');
    await expect(page.getByTestId('week-total')).toHaveText('3:45');

    await page.reload();
    await expect(cell('2026-11-02')).toHaveValue('1:30');
    await cell('2026-11-02').fill('8');
    await cell('2026-11-02').press('Tab');
    await expect(page.getByTestId('week-total')).toHaveText('10:15');
    await page.getByTestId('undo').click();
    await expect(page.getByTestId('week-total')).toHaveText('3:45');

    await cell('2026-11-04').fill('abc');
    await cell('2026-11-04').press('Tab');
    await expect(cell('2026-11-04')).toHaveAttribute('aria-invalid', 'true');
    await cell('2026-11-04').fill('');

    await page.getByTestId('submit-week').click();
    await expect(page.getByText('2 unosa poslana')).toBeVisible();
    await expect(cell('2026-11-02')).toHaveValue('');
});

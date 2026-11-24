/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { expect, test } from '@playwright/test';
import { signIn } from './support';

// S-0953 to S-0957: a report of a project, downloaded in every format.
test('generates a service report', async ({ page }) => {
    await signIn(page, 'reports');
    await page.goto('/service-reports');
    await page.getByTestId('new-report').click();
    // Approved time of last month comes from E2eSeeder.
    await page
        .getByRole('dialog')
        .locator('[name="project_id"]')
        .selectOption({ label: 'E2E-1 Report project' });
    await page.getByTestId('dialog-save').click();
    const row = page.getByRole('row').filter({ hasText: 'Report project' });
    await expect(row).toBeVisible();

    for (const format of ['pdf', 'docx', 'xlsx', 'csv']) {
        const download = page.waitForEvent('download');
        await row.locator(`a[href$="/${format}"]`).click();
        expect((await download).suggestedFilename()).toMatch(
            new RegExp(`\\.${format}$`),
        );
        expect(await (await download).failure()).toBeNull();
    }
});

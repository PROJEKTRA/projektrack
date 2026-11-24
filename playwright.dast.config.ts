/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import base from './playwright.config';

/*
 * Attack scan of the running application (security baseline 9, DAST):
 * `npx playwright test -c playwright.dast.config.ts`. Needs Docker on Linux.
 */
export default {
    ...base,
    testDir: 'tests/dast',
    timeout: 1_800_000,
};

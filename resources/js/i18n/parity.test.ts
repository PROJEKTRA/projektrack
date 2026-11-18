/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { t, useBundle } from './strings';

/*
 * PHP and browser formatting parity (S-0917, ADR-0005 section 3). The same
 * corpus runs in tests/Feature/I18n/QualityGatesTest.php against ext-intl.
 */

type ParityCase = {
    locale: string;
    message: string;
    args: Record<string, string | number>;
    expected: string;
};

const corpus = JSON.parse(
    readFileSync('tests/fixtures/i18n/parity.json', 'utf8'),
) as { cases: ParityCase[] };

describe('formatting parity with the server', () => {
    it.each(corpus.cases)(
        '$locale: $message with $args',
        ({ locale, message, args, expected }) => {
            useBundle({
                locale,
                dir: 'ltr',
                version: 'parity',
                messages: { 'test:case': message },
                sources: {},
                reserved: {},
            });

            expect(t('test:case', args)).toBe(expected);
        },
    );
});

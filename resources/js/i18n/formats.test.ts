/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import {
    formatDate,
    formatDateTime,
    formatDay,
    formatMoney,
    formatNumber,
    formatTime,
} from './formats';
import type { FormatsMeta } from './formats';

/* S-0919: same output as App\I18n\RegionalFormat (tests/Feature/I18n/FormatsTest.php). */

type Case = {
    name: string;
    fn: 'date' | 'day' | 'time' | 'dateTime' | 'number' | 'money';
    locale: string;
    pattern: string | null;
    timeZone: string;
    input: string | number;
    decimals?: number;
    currency?: string;
    expect: string;
};

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const { cases } = JSON.parse(
    readFileSync(resolve(ROOT, 'tests/fixtures/i18n/formats.json'), 'utf8'),
) as { cases: Case[] };

/** ICU versions differ in narrow and normal no-break spaces. */
const spaces = (text: string): string => text.replace(/[\s ]+/g, ' ');

function run(test: Case): string {
    const meta: FormatsMeta = {
        locale: test.locale,
        timeZone: test.timeZone,
        datePattern: test.pattern,
    };

    switch (test.fn) {
        case 'date':
            return formatDate(String(test.input), meta);
        case 'day':
            return formatDay(String(test.input), meta);
        case 'time':
            return formatTime(String(test.input), meta);
        case 'dateTime':
            return formatDateTime(String(test.input), meta);
        case 'number':
            return formatNumber(Number(test.input), test.decimals ?? 0, meta);
        case 'money':
            return formatMoney(Number(test.input), test.currency ?? '', meta);
    }
}

describe('regional formats', () => {
    it.each(cases.map((test) => [test.name, test] as const))(
        '%s',
        (_name, test) => {
            expect(spaces(run(test))).toBe(spaces(test.expect));
        },
    );

    it('refuses amounts that are not integers in minor units', () => {
        const meta: FormatsMeta = {
            locale: 'bs-BA',
            timeZone: 'UTC',
            datePattern: null,
        };

        expect(() => formatMoney(12.5, 'EUR', meta)).toThrow(RangeError);
        expect(() => formatMoney(2 ** 52, 'EUR', meta)).toThrow(RangeError);
    });
});

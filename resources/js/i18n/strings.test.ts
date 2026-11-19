/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { IntlMessageFormat } from 'intl-messageformat';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setBrand, t, useBundle } from './strings';
import type { StringBundle } from './strings';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const JS_ROOT = join(ROOT, 'resources', 'js');
const GENERATED = new Set(['actions', 'routes', 'wayfinder']);
const KEY_FORMAT = /^[a-z][a-z0-9_]*(\.[a-z0-9_]+)*$/;
const LOCALES = ['en', 'bs'];

function readRaw(locale: string): string {
    return readFileSync(join(ROOT, 'lang', locale, 'core.json'), 'utf8');
}

function readCore(locale: string): Record<string, string> {
    // Strip a BOM here so the dedicated test reports it instead of a JSON error.
    return JSON.parse(readRaw(locale).replace(/^﻿/, '')) as Record<
        string,
        string
    >;
}

function sourceFiles(dir: string): string[] {
    const files: string[] = [];

    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            if (dir === JS_ROOT && GENERATED.has(entry.name)) {
                continue;
            }

            files.push(...sourceFiles(path));
        } else if (
            /\.(vue|ts)$/.test(entry.name) &&
            !entry.name.endsWith('.test.ts')
        ) {
            files.push(path);
        }
    }

    return files;
}

describe('core string files', () => {
    const catalogs: Record<string, Record<string, string>> = Object.fromEntries(
        LOCALES.map((locale) => [locale, readCore(locale)]),
    );

    it.each(LOCALES)('are UTF-8 without a byte order mark (%s)', (locale) => {
        expect(readRaw(locale).charCodeAt(0)).not.toBe(0xfeff);
    });

    it('have exactly the same keys in every language', () => {
        const en = Object.keys(catalogs.en).sort();
        const bs = Object.keys(catalogs.bs).sort();

        expect(bs.filter((key) => !en.includes(key))).toEqual([]);
        expect(en.filter((key) => !bs.includes(key))).toEqual([]);
    });

    it('use valid keys', () => {
        for (const key of Object.keys(catalogs.en)) {
            expect(KEY_FORMAT.test(key), key).toBe(true);
            expect(key.length, key).toBeLessThanOrEqual(100);
        }
    });

    it.each(LOCALES)('contain only valid ICU messages (%s)', (locale) => {
        for (const [key, message] of Object.entries(catalogs[locale])) {
            expect(typeof message, key).toBe('string');
            expect(message.includes('\u2013'), `${key} has an en dash`).toBe(
                false,
            );
            // Same options as the runtime formatter in strings.ts.
            expect(
                () =>
                    new IntlMessageFormat(message, locale, undefined, {
                        ignoreTag: true,
                    }),
                `${locale}: ${key}`,
            ).not.toThrow();
        }
    });

    it('define every identifier the frontend uses', () => {
        const used = new Map<string, string>();
        const call = /\bt\(\s*['"`]core:([^'"`]+)['"`]/g;

        for (const file of sourceFiles(JS_ROOT)) {
            const source = readFileSync(file, 'utf8');

            for (const match of source.matchAll(call)) {
                used.set(match[1], relative(ROOT, file).split(sep).join('/'));
            }
        }

        expect(used.size).toBeGreaterThan(0);

        const missing = [...used]
            .filter(([key]) => !(key in catalogs.en))
            .map(([key, file]) => `${key} (${file})`);

        expect(missing).toEqual([]);
    });
});

describe('t()', () => {
    const bundle: StringBundle = {
        locale: 'bs',
        dir: 'ltr',
        version: 'test',
        messages: {
            'core:string_missing': 'Tekst nedostaje',
            'core:test.hours':
                '{count, plural, one {# sat} few {# sata} other {# sati}}',
            'core:test.hours_en':
                '{count, plural, one {# hour} other {# hours}}',
            'core:test.welcome': 'Dobrodošli u {brand}, {name}',
        },
        sources: {
            'core:test.hours_en': 'en',
        },
        reserved: {
            brand: 'Primjer',
        },
    };

    beforeEach(() => {
        vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        useBundle(bundle);
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it.each([
        [1, '1 sat'],
        [2, '2 sata'],
        [5, '5 sati'],
        [11, '11 sati'],
        [21, '21 sat'],
        [22, '22 sata'],
        [25, '25 sati'],
    ])('uses Bosnian plural rules for %i', (count, expected) => {
        expect(t('core:test.hours', { count })).toBe(expected);
    });

    it('shows the missing text message instead of the identifier', () => {
        const result = t('core:test.not_defined_anywhere');

        expect(result).toBe('Tekst nedostaje');
        expect(result).not.toContain('core:');
    });

    it('formats a fallback message with the plural rules of its source', () => {
        // Bosnian would pick "one" for 21; English picks "other".
        expect(t('core:test.hours_en', { count: 21 })).toBe('21 hours');
        expect(t('core:test.hours_en', { count: 1 })).toBe('1 hour');
    });

    it('injects reserved arguments such as the brand', () => {
        expect(t('core:test.welcome', { name: 'Ana' })).toBe(
            'Dobrodošli u Primjer, Ana',
        );
    });
});

/* S-0920: arguments keep their order inside right to left text. */
describe('bidi isolation', () => {
    function bundleOf(locale: string, dir: 'ltr' | 'rtl'): StringBundle {
        return {
            locale,
            dir,
            version: `${locale}-test`,
            messages: {
                'core:test.key': 'Ticket {key}',
                'core:test.en': 'Hi {name}',
            },
            sources: { 'core:test.en': 'en' },
            reserved: {},
        };
    }

    it('wraps arguments in isolates for shipped and own right to left languages', () => {
        for (const locale of ['ar', 'ur']) {
            useBundle(bundleOf(locale, 'rtl'));

            expect(t('core:test.key', { key: 'PTK-123' })).toBe(
                'Ticket ⁨PTK-123⁩',
            );
        }
    });

    it('leaves arguments alone in left to right messages', () => {
        useBundle(bundleOf('bs', 'ltr'));
        expect(t('core:test.key', { key: 'PTK-123' })).toBe('Ticket PTK-123');

        // A fallback message from en inside an RTL language is left to right.
        useBundle(bundleOf('ur', 'rtl'));
        expect(t('core:test.en', { name: 'Ana' })).toBe('Hi Ana');
    });
});

/* S-0911: the public bundle never carries the product name; the page sets it. */
describe('brand', () => {
    it('uses the brand of the page for {brand}, else the bundle value', () => {
        useBundle({
            locale: 'en',
            dir: 'ltr',
            version: 'brand-test',
            messages: { 'core:test.brand': 'Welcome to {brand}' },
            sources: {},
            reserved: { brand: '' },
        });

        setBrand('Acme Projects');
        expect(t('core:test.brand')).toBe('Welcome to Acme Projects');

        setBrand(null);
        expect(t('core:test.brand')).toBe('Welcome to ');
    });
});

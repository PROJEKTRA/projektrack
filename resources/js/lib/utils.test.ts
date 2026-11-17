/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { describe, expect, it } from 'vitest';
import { cn, toUrl } from './utils';

describe('cn', () => {
    it('merges conflicting utilities so the last one wins', () => {
        expect(cn('ps-2', 'ps-4')).toBe('ps-4');
    });

    it('drops falsy values', () => {
        expect(cn('text-start', false && 'hidden', undefined)).toBe(
            'text-start',
        );
    });
});

describe('toUrl', () => {
    it('returns string hrefs unchanged', () => {
        expect(toUrl('/dashboard')).toBe('/dashboard');
    });

    it('reads the url of route objects', () => {
        expect(toUrl({ url: '/settings', method: 'get' })).toBe('/settings');
    });
});

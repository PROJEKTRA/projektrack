/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { describe, expect, it } from 'vitest';
import { formatHours, parseHours } from './hours';

describe('hours', () => {
    it.each([
        ['1,5', 90],
        ['1.5', 90],
        ['1:30', 90],
        ['8', 480],
        ['0,25', 15],
        ['0.33', 20],
        ['0.1', 6],
        [' 2 ', 120],
        ['0', 0],
        ['1:60', null],
        ['-1', null],
        ['abc', null],
        ['', null],
        ['1,234', null],
    ])('reads %s', (text, minutes) => {
        expect(parseHours(text)).toBe(minutes);
    });

    it('writes hours and minutes', () => {
        expect(formatHours(90)).toBe('1:30');
        expect(formatHours(-60)).toBe('-1:00');
        expect(formatHours(5)).toBe('0:05');
    });
});

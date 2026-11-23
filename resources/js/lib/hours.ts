/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/** "1:30", "1,5", "1.5" or "2" as whole minutes; null if it is none of them. */
export function parseHours(text: string): number | null {
    const value = text.trim();
    const clock = /^(\d{1,5}):([0-5]\d)$/.exec(value);

    if (clock) {
        return Number(clock[1]) * 60 + Number(clock[2]);
    }

    const decimal = /^(\d{1,5})(?:[.,](\d{1,2}))?$/.exec(value);

    if (!decimal) {
        return null;
    }

    // Hundredths of an hour in whole numbers, half a minute up.
    const hundredths =
        Number(decimal[1]) * 100 + Number((decimal[2] ?? '').padEnd(2, '0'));

    return Math.floor((hundredths * 60 + 50) / 100);
}

/** 90 as "1:30". */
export function formatHours(minutes: number): string {
    const sign = minutes < 0 ? '-' : '';
    const value = Math.abs(minutes);

    return `${sign}${Math.floor(value / 60)}:${String(value % 60).padStart(2, '0')}`;
}

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { shallowRef } from 'vue';

/*
 * Dates, times, numbers and amounts in the user's regional format and time
 * zone (S-0919, ADR-0005 section 3). Same output as App\I18n\RegionalFormat
 * on the server (tests/fixtures/i18n/formats.json). Timestamps are shown in
 * the time zone; calendar dates (Y-m-d) are never shifted.
 */

/** Shared Inertia prop `formats` (App\I18n\RegionalFormat::toArray). */
export type FormatsMeta = {
    locale: string;
    timeZone: string;
    /** Numeric date pattern replacing the CLDR one, e.g. dd.MM.yyyy. */
    datePattern: string | null;
};

const current = shallowRef<FormatsMeta>({
    locale: 'en-GB',
    timeZone: 'UTC',
    datePattern: null,
});

/** Use the format of the current page (app.ts, on every visit). */
export function setFormats(meta: FormatsMeta | null | undefined): void {
    if (meta) {
        current.value = meta;
    }
}

function toDate(at: Date | string): Date {
    return at instanceof Date ? at : new Date(at);
}

function numericDate(date: Date, meta: FormatsMeta, timeZone: string): string {
    const formatter = new Intl.DateTimeFormat(meta.locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone,
    });

    if (!meta.datePattern) {
        return formatter.format(date);
    }

    const parts = Object.fromEntries(
        formatter.formatToParts(date).map((part) => [part.type, part.value]),
    );
    const tokens: Record<string, string> = {
        dd: parts.day ?? '',
        d: String(Number(parts.day)),
        MM: parts.month ?? '',
        M: String(Number(parts.month)),
        yyyy: parts.year ?? '',
        yy: (parts.year ?? '').slice(-2),
    };

    return meta.datePattern.replace(
        /yyyy|yy|MM|M|dd|d/g,
        (token) => tokens[token] ?? token,
    );
}

/** Day of a timestamp in the time zone: 01.11.2026 (bs), 11/01/2026 (en-US). */
export function formatDate(
    at: Date | string,
    meta: FormatsMeta = current.value,
): string {
    return numericDate(toDate(at), meta, meta.timeZone);
}

/** A calendar date (Y-m-d), never shifted by the time zone. */
export function formatDay(
    date: string,
    meta: FormatsMeta = current.value,
): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);

    if (!match) {
        throw new RangeError(`Not a date (Y-m-d): ${date}`);
    }

    const day = new Date(
        Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])),
    );

    return numericDate(day, meta, 'UTC');
}

/** Time of a timestamp in the time zone: 15:05 (bs), 3:05 PM (en-US). */
export function formatTime(
    at: Date | string,
    meta: FormatsMeta = current.value,
): string {
    // 24-hour locales show two-digit hours (15:05, 02:30), as CLDR does on the server.
    const { hourCycle } = new Intl.DateTimeFormat(meta.locale, {
        hour: 'numeric',
    }).resolvedOptions();
    const twoDigits = hourCycle === 'h23' || hourCycle === 'h24';

    return new Intl.DateTimeFormat(meta.locale, {
        hour: twoDigits ? '2-digit' : 'numeric',
        minute: '2-digit',
        timeZone: meta.timeZone,
    }).format(toDate(at));
}

/** Date and time of a timestamp in the time zone. */
export function formatDateTime(
    at: Date | string,
    meta: FormatsMeta = current.value,
): string {
    return `${formatDate(at, meta)} ${formatTime(at, meta)}`;
}

/** A number with a fixed number of decimals: 1.234,50 (bs). */
export function formatNumber(
    value: number,
    decimals = 0,
    meta: FormatsMeta = current.value,
): string {
    return new Intl.NumberFormat(meta.locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
}

/** Minor unit digits of a currency (EUR 2, JPY 0, KWD 3). */
export function fractionDigits(currency: string): number {
    return (
        new Intl.NumberFormat('en', {
            style: 'currency',
            currency,
        }).resolvedOptions().maximumFractionDigits ?? 2
    );
}

/**
 * An amount in minor units with its ISO currency code: 1.234,50 EUR (bs).
 * Money stays an integer until this display step.
 */
export function formatMoney(
    minor: number,
    currency: string,
    meta: FormatsMeta = current.value,
): string {
    // Below 2^52 the division rounds back to the same digits (as on the server).
    if (!Number.isInteger(minor) || Math.abs(minor) >= 2 ** 52) {
        throw new RangeError(
            'Amounts are integers in minor units, below 2^52.',
        );
    }

    const digits = fractionDigits(currency);

    return new Intl.NumberFormat(meta.locale, {
        style: 'currency',
        currency: currency.toUpperCase(),
        currencyDisplay: 'code',
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(minor / 10 ** digits);
}

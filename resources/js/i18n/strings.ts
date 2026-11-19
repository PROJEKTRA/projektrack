/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { IntlMessageFormat } from 'intl-messageformat';
import type { PrimitiveType } from 'intl-messageformat';
import { computed, shallowRef } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Client side of the string system (S-0916, ADR-0005 section 3).
 *
 * The server resolves every identifier through the fallback chain and sends one
 * versioned bundle per chain. This module only formats: the same ICU messages
 * as on the server, with the browser's CLDR plural rules.
 */

export type StringArgs = Record<string, PrimitiveType>;

/** Shared Inertia prop that names the current bundle. */
export type StringsMeta = {
    locale: string;
    dir: 'ltr' | 'rtl';
    url: string;
    version: string;
};

export type StringBundle = {
    locale: string;
    dir: 'ltr' | 'rtl';
    version: string;
    /** Identifier to ICU message, already resolved through the chain. */
    messages: Record<string, string>;
    /** Locale a message came from, only where it differs from `locale` (fallback). */
    sources: Record<string, string>;
    /** Values for reserved arguments such as {brand}. */
    reserved: Record<string, string>;
};

const MISSING = 'core:string_missing';

const bundle = shallowRef<StringBundle | null>(null);
// {brand} for signed-in users, from the page (S-0911); the public bundle never carries the product name.
const brandName = shallowRef<string | null>(null);

/** Use the brand of the current page for {brand}. */
export function setBrand(name: string | null | undefined): void {
    brandName.value = name || null;
}
const formatters = new Map<string, IntlMessageFormat>();
const reported = new Set<string>();
const RTL_LOCALES = new Set(['ar', 'fa', 'xx-rtl']);
let loading: Promise<void> | null = null;
let loadingUrl: string | null = null;

/** Use a bundle that is already in memory (tests, server-provided data). */
export function useBundle(next: StringBundle): void {
    formatters.clear();
    bundle.value = next;
}

/**
 * Load the bundle named by the shared prop unless it is already active.
 * Bundles are immutable per version, so the browser cache serves repeats.
 */
export function loadStrings(
    meta: StringsMeta | null | undefined,
): Promise<void> {
    if (!meta || bundle.value?.version === meta.version) {
        return Promise.resolve();
    }

    if (loading && loadingUrl === meta.url) {
        return loading;
    }

    loadingUrl = meta.url;
    loading = fetch(meta.url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })
        .then(async (response) => {
            if (!response.ok) {
                throw new Error(
                    `String bundle ${meta.url}: HTTP ${response.status}`,
                );
            }

            useBundle((await response.json()) as StringBundle);
        })
        .finally(() => {
            loading = null;
            loadingUrl = null;
        });

    return loading;
}

function formatter(
    id: string,
    message: string,
    locale: string,
): IntlMessageFormat | null {
    const key = `${locale}\u0000${id}`;
    const cached = formatters.get(key);

    if (cached) {
        return cached;
    }

    try {
        // Pseudo-locales (xx, xx-rtl) use the plural rules of en, like the server.
        const formatLocale = locale.startsWith('xx') ? 'en' : locale;
        const created = new IntlMessageFormat(
            message,
            formatLocale,
            undefined,
            {
                ignoreTag: true,
            },
        );
        formatters.set(key, created);

        return created;
    } catch (error) {
        report(id, `invalid message: ${String(error)}`);

        return null;
    }
}

function report(id: string, reason: string): void {
    if (import.meta.env.DEV && !reported.has(id)) {
        reported.add(id);
        console.warn(`[strings] ${id}: ${reason}`);
    }
}

/** Whether a message locale is written right to left (own RTL languages included). */
function isRtl(locale: string, current: StringBundle): boolean {
    if (locale === current.locale) {
        return current.dir === 'rtl';
    }

    return RTL_LOCALES.has(locale) || RTL_LOCALES.has(locale.split('-')[0]);
}

/** Wrap arguments in Unicode isolates for right-to-left locales (ADR-0005 section 8). */
function isolate(
    args: StringArgs,
    locale: string,
    current: StringBundle,
): StringArgs {
    if (!isRtl(locale, current)) {
        return args;
    }

    const isolated: StringArgs = {};
    for (const [name, value] of Object.entries(args)) {
        isolated[name] = typeof value === 'string' ? `⁨${value}⁩` : value;
    }

    return isolated;
}

/**
 * Resolve and format a string. Never returns the identifier itself: a string
 * that is missing everywhere shows the "text missing" message instead.
 */
export function t(id: string, args: StringArgs = {}): string {
    const current = bundle.value;

    if (!current) {
        return '';
    }

    const message = current.messages[id];

    if (message === undefined) {
        report(id, 'not found in any language of the chain');

        return id === MISSING ? '' : t(MISSING);
    }

    const locale = current.sources[id] ?? current.locale;
    const compiled = formatter(id, message, locale);

    if (!compiled) {
        return id === MISSING ? '' : t(MISSING);
    }

    const result = compiled.format({
        ...current.reserved,
        ...(brandName.value ? { brand: brandName.value } : {}),
        ...isolate(args, locale, current),
    });

    return Array.isArray(result) ? result.join('') : String(result);
}

/** True when the identifier exists in the active bundle. */
export function hasString(id: string): boolean {
    return bundle.value?.messages[id] !== undefined;
}

export function useStrings(): {
    t: typeof t;
    locale: ComputedRef<string>;
    dir: ComputedRef<'ltr' | 'rtl'>;
} {
    return {
        t,
        locale: computed(
            () => bundle.value?.locale ?? document.documentElement.lang,
        ),
        dir: computed(() => bundle.value?.dir ?? 'ltr'),
    };
}

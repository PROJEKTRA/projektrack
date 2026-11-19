/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/**
 * Nonce of the page's Content Security Policy (S-0909), for libraries that
 * add a <style> element at runtime (progress bar, one-time code input).
 * Browsers hide the nonce attribute from scripts; the property stays readable.
 */
export function cspNonce(): string | undefined {
    if (typeof document === 'undefined') {
        return undefined;
    }

    const meta = document.querySelector<HTMLMetaElement>(
        'meta[property="csp-nonce"]',
    );

    return meta?.nonce || undefined;
}

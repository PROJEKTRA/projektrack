/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

export type JsonResult<T> =
    { ok: true; data: T } | { ok: false; message: string | null };

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

/** A small request outside page visits; the first validation message on refusal. */
export async function requestJson<T>(
    method: 'POST' | 'PUT',
    url: string,
    body: unknown,
): Promise<JsonResult<T>> {
    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(body),
        });
        const data: unknown = await response.json();

        if (response.ok) {
            return { ok: true, data: data as T };
        }

        const errors = (data as { errors?: Record<string, string[]> }).errors;

        return {
            ok: false,
            message: errors ? (Object.values(errors)[0]?.[0] ?? null) : null,
        };
    } catch {
        return { ok: false, message: null };
    }
}

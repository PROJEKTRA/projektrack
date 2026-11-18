/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { useDirection } from 'reka-ui';
import { computed, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';

/*
 * Writing direction helpers (S-0920, ADR-0005 section 7).
 *
 * Components with a physical `side` prop (sheet, sidebar, tooltip and popover
 * placement) are written for left to right. In a right to left language the
 * side is mirrored, so "left" means "at the start of the reading direction".
 */

export type Direction = 'ltr' | 'rtl';
export type Side = 'top' | 'right' | 'bottom' | 'left';

/** The side as given for left to right, mirrored for right to left. */
export function mirrorSide<T extends Side>(side: T, dir: Direction): T {
    if (dir !== 'rtl') {
        return side;
    }

    if (side === 'left') {
        return 'right' as T;
    }

    if (side === 'right') {
        return 'left' as T;
    }

    return side;
}

/** Reactive mirrorSide() using the direction of the nearest ConfigProvider. */
export function useMirroredSide<T extends Side>(
    side: MaybeRefOrGetter<T>,
): ComputedRef<T> {
    const dir = useDirection();

    return computed(() => mirrorSide(toValue(side), dir.value));
}

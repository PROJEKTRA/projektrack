/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import {
    ArrowLeft,
    ChevronRight,
    LogOut,
    PanelLeftClose,
    PanelLeftOpen,
} from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { ConfigProvider } from 'reka-ui';
import { defineComponent, h } from 'vue';
import { describe, expect, it } from 'vitest';
import { mirrorSide, useMirroredSide } from './direction';
import type { Side } from './direction';

/* S-0920: sides and icons follow the writing direction. */

describe('mirrorSide', () => {
    it('keeps every side in left to right languages', () => {
        for (const side of ['top', 'right', 'bottom', 'left'] as Side[]) {
            expect(mirrorSide(side, 'ltr')).toBe(side);
        }
    });

    it('swaps left and right in right to left languages', () => {
        expect(mirrorSide('left', 'rtl')).toBe('right');
        expect(mirrorSide('right', 'rtl')).toBe('left');
        expect(mirrorSide('top', 'rtl')).toBe('top');
        expect(mirrorSide('bottom', 'rtl')).toBe('bottom');
    });
});

describe('useMirroredSide', () => {
    const Probe = defineComponent({
        setup() {
            const side = useMirroredSide('left');

            return () => h('span', side.value);
        },
    });

    it('reads the direction of the ConfigProvider', () => {
        const rtl = mount(() =>
            h(ConfigProvider, { dir: 'rtl' }, () => h(Probe)),
        );
        const ltr = mount(() =>
            h(ConfigProvider, { dir: 'ltr' }, () => h(Probe)),
        );

        expect(rtl.text()).toBe('right');
        expect(ltr.text()).toBe('left');
    });
});

describe('directional icons', () => {
    it('render the class names resources/css/rtl.css mirrors', () => {
        const expected = [
            [ArrowLeft, 'lucide-arrow-left'],
            [ChevronRight, 'lucide-chevron-right'],
            [LogOut, 'lucide-log-out'],
            [PanelLeftClose, 'lucide-panel-left-close'],
            [PanelLeftOpen, 'lucide-panel-left-open'],
        ] as const;

        for (const [icon, className] of expected) {
            expect(mount(icon).classes()).toContain(className);
        }
    });
});

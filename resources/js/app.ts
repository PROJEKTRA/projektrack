/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { loadStrings, t } from '@/i18n/strings';
import type { StringsMeta } from '@/i18n/strings';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Projektrack';

function initialStrings(): StringsMeta | null {
    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="strings-bundle"]',
    );

    if (!meta?.content) {
        return null;
    }

    return {
        url: meta.content,
        version: meta.dataset.version ?? '',
        locale: meta.dataset.locale ?? document.documentElement.lang,
        dir: meta.dataset.dir === 'rtl' ? 'rtl' : 'ltr',
    };
}

// A language change (profile, tenant) arrives as a new bundle version.
router.on('navigate', (event) => {
    const meta = event.detail.page.props.strings as StringsMeta | undefined;

    if (meta) {
        document.documentElement.lang = meta.locale;
        document.documentElement.dir = meta.dir;
        void loadStrings(meta);
    }
});

async function boot(): Promise<void> {
    try {
        await loadStrings(initialStrings());
    } catch (error) {
        // Render anyway: missing strings show as empty, never as identifiers.
        console.error(error);
    }

    await createInertiaApp({
        title: (title) => (title ? `${title} - ${appName}` : appName),
        layout: (name) => {
            switch (true) {
                case name === 'Welcome':
                    return null;
                case name.startsWith('auth/'):
                    return AuthLayout;
                case name.startsWith('settings/'):
                    return [AppLayout, SettingsLayout];
                default:
                    return AppLayout;
            }
        },
        withApp: (app) => {
            app.config.globalProperties.$t = t;
            app.directive('focus', {
                mounted: (el: HTMLElement, shouldFocus) => {
                    if (shouldFocus.value !== false) {
                        el.focus();
                    }
                },
            });
        },
        progress: {
            color: '#4B5563',
        },
    });
}

void boot();

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ConfigProvider } from 'reka-ui';
import { createApp, h, ref } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import { setFormats } from '@/i18n/formats';
import type { FormatsMeta } from '@/i18n/formats';
import { loadStrings, setBrand, t } from '@/i18n/strings';
import type { StringsMeta } from '@/i18n/strings';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import SharedLayout from '@/layouts/SharedLayout.vue';
import { cspNonce } from '@/lib/csp';
import { initializeFlashToast } from '@/lib/flashToast';

// Brand of the tenant for page titles (S-0911), updated with every visit.
const brandName = ref('');
// Writing direction of the user's language, switched together with html dir.
const dir = ref<'ltr' | 'rtl'>(
    document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr',
);

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
        dir.value = meta.dir;
        void loadStrings(meta);
    }

    setFormats(event.detail.page.props.formats as FormatsMeta | undefined);
    brandName.value = String(event.detail.page.props.name ?? '');
    setBrand(brandName.value);
});

async function boot(): Promise<void> {
    try {
        await loadStrings(initialStrings());
    } catch (error) {
        // Render anyway: missing strings show as empty, never as identifiers.
        console.error(error);
    }

    await createInertiaApp({
        title: (title) =>
            title ? `${title} - ${brandName.value}` : brandName.value,
        layout: (name) => {
            switch (true) {
                case name === 'Welcome':
                    return null;
                case name.startsWith('auth/'):
                    return AuthLayout;
                case name.startsWith('shared/'):
                    return SharedLayout;
                case name.startsWith('settings/'):
                    return [AppLayout, SettingsLayout];
                default:
                    return AppLayout;
            }
        },
        setup: ({ el, App, props, plugin }) => {
            setFormats(
                props.initialPage.props.formats as FormatsMeta | undefined,
            );
            brandName.value = String(props.initialPage.props.name ?? '');
            setBrand(brandName.value);

            // Reka UI primitives (menus, popovers, keyboard navigation) read
            // the writing direction of the user's language from here (S-0920).
            const app = createApp({
                render: () =>
                    h(ConfigProvider, { dir: dir.value }, () => h(App, props)),
            });

            app.use(plugin);
            app.config.globalProperties.$t = t;
            app.directive('focus', {
                mounted: (element: HTMLElement, shouldFocus) => {
                    if (shouldFocus.value !== false) {
                        element.focus();
                    }
                },
            });
            if (el) {
                app.mount(el);
            }
        },
        // Runtime <style> elements of Inertia carry the CSP nonce (S-0909).
        nonce: cspNonce(),
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

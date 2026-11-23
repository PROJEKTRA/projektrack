/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import type { Directive } from 'vue';
import type { FormatsMeta } from '@/i18n/formats';
import type { StringsMeta, t } from '@/i18n/strings';
import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Set when the audit log failed its integrity check (core.audit.view only). */
            auditWarning: string | null;
            /** Current string bundle (S-0916). */
            strings: StringsMeta;
            formats: FormatsMeta;
            unreadNotifications: number;
            /** Critical system check findings, for administrators (S-0988). */
            doctorAlerts: number | null;
            /** May open Admin > License (S-0976). */
            licenseAccess: boolean;
            /** Brand of the tenant (S-0911). */
            brand: {
                name: string;
                logoUrl: string | null;
                licensorMark: boolean;
                poweredBy: boolean;
            };
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
        /** Resolve a string identifier such as `core:save` (S-0916). */
        $t: typeof t;
    }
}

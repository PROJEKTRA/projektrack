/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import prettier from '@vue/eslint-config-prettier';
import {
    defineConfigWithVueTs,
    vueTsConfigs,
} from '@vue/eslint-config-typescript';
import vue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'storage/**',
            'docs/**',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'resources/js/components/ui/**',
        ],
    },
    vue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            // Output is escaped by Vue; raw HTML would bypass it (S-0909, security baseline).
            'vue/no-v-html': 'error',
            '@typescript-eslint/consistent-type-imports': 'error',
            // No user-facing text in templates: use $t('<component>:<key>') (S-0917).
            'vue/no-bare-strings-in-template': [
                'error',
                {
                    attributes: {
                        '/.+/': [
                            'title',
                            'aria-label',
                            'aria-placeholder',
                            'aria-roledescription',
                            'aria-valuetext',
                            'aria-description',
                            'placeholder',
                            'alt',
                            'label',
                            'description',
                        ],
                    },
                    directives: ['v-text'],
                },
            ],
            // Layout must use logical directions so RTL works (ADR on i18n and RTL).
            'no-restricted-syntax': [
                'error',
                {
                    selector:
                        'Literal[value=/(^|\\s)-?(ml|mr|pl|pr|left|right|border-l|border-r|rounded-l|rounded-r|text-left|text-right)-/]',
                    message:
                        'Use logical Tailwind utilities (ms-, me-, ps-, pe-, start-, end-, text-start, text-end).',
                },
            ],
        },
    },
    {
        // The i18n checker names the physical utilities it rejects.
        files: ['scripts/i18n/**'],
        rules: { 'no-restricted-syntax': 'off' },
    },
    prettier,
);

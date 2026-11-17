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
            '@typescript-eslint/consistent-type-imports': 'error',
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
    prettier,
);

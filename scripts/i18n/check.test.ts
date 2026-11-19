// @vitest-environment node
/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { readFileSync } from 'node:fs';
import { ESLint } from 'eslint';
import { describe, expect, it } from 'vitest';
import {
    checkBrandNames,
    checkCatalogs,
    checkDirections,
    checkIcons,
    checkRefusedLocales,
    checkUsage,
    fixDirections,
    iconName,
    mirroredIcons,
} from './check.ts';

/* S-0917: i18n quality gates. */

// Typed ESLint needs a few seconds to start, more on a busy CI runner.
describe('hard-coded strings', { timeout: 30_000 }, () => {
    it('fails the lint step on a literal text in a Vue template, with file and line', async () => {
        const eslint = new ESLint({ cwd: process.cwd() });
        const [result] = await eslint.lintText(
            '<template>\n    <div>\n        <button>Save</button>\n    </div>\n</template>\n',
            // An existing path: typed rules need a file of the TypeScript project.
            { filePath: 'resources/js/pages/Dashboard.vue' },
        );

        const bare = result.messages.filter(
            (m) => m.ruleId === 'vue/no-bare-strings-in-template',
        );
        expect(bare).toHaveLength(1);
        expect(bare[0].line).toBe(3);
    });

    it('also catches labels in attributes and accepts identifiers', async () => {
        const eslint = new ESLint({ cwd: process.cwd() });
        const [result] = await eslint.lintText(
            '<template>\n    <input placeholder="Email" />\n    <button :aria-label="$t(\'core:common.close\')">{{ $t(\'core:common.save\') }}</button>\n</template>\n',
            // An existing path: typed rules need a file of the TypeScript project.
            { filePath: 'resources/js/pages/Dashboard.vue' },
        );

        const lines = result.messages
            .filter((m) => m.ruleId === 'vue/no-bare-strings-in-template')
            .map((m) => m.line);
        expect(lines).toEqual([2]);
    });
});

describe('missing identifiers', () => {
    const catalogs = {
        en: { 'core:save': 'Save', 'core:only_en': 'Only en' },
        bs: { 'core:save': 'Sačuvaj' },
    };

    it('names an identifier used in code but missing in bs', () => {
        const problems = checkUsage(
            'resources/js/pages/Fixture.vue',
            "<template>\n  {{ $t('core:save') }}\n  {{ $t('core:only_en') }}\n</template>",
            catalogs,
            new Set(['core']),
        );

        expect(problems).toEqual([
            {
                file: 'resources/js/pages/Fixture.vue',
                line: 3,
                message: 'core:only_en is used here but missing in lang/bs',
            },
        ]);
    });

    it('finds identifiers in PHP and ignores other colon strings', () => {
        const php =
            "<?php\n__('core:missing');\n$strings->get('core:save');\nRoute::middleware('permission:core.users.manage');";

        expect(
            checkUsage('app/X.php', php, catalogs, new Set(['core'])).map(
                (p) => p.line,
            ),
        ).toEqual([2, 2]);
    });

    it('reports missing bs strings, orphans, invalid ICU and undeclared arguments', () => {
        const messages = checkCatalogs(
            {
                en: {
                    'core:a': 'A',
                    'core:b': 'Hello {name}',
                    'core:c': '{n, plural, one {#} other {#}}',
                },
                bs: {
                    'core:b': 'Zdravo {ime}',
                    'core:c': '{n, plural, one {#',
                    'core:x': 'Orphan',
                },
            },
            (locale) => `lang/${locale}/core.json`,
        ).map((p) => p.message);

        expect(messages).toEqual(
            expect.arrayContaining([
                'core:a (bs): missing in lang/bs',
                'core:x (bs): not defined in en (orphan)',
                'core:b (bs): uses argument {ime} that the en message does not declare',
            ]),
        );
        expect(
            messages.some((m) => m.startsWith('core:c (bs): not valid ICU')),
        ).toBe(true);
    });

    it('requires every CLDR plural category of the locale', () => {
        const messages = checkCatalogs(
            {
                en: {
                    'core:h': '{count, plural, one {# hour} other {# hours}}',
                },
                bs: { 'core:h': '{count, plural, one {# sat} other {# sati}}' },
            },
            () => 'lang',
        ).map((p) => p.message);

        expect(messages).toEqual([
            'core:h (bs): plural lacks the category required in bs: count: few',
        ]);
    });

    it('refuses Hebrew locale folders', () => {
        expect(
            checkRefusedLocales([
                'lang/he/core.json',
                'modules/Timesheets/lang/iw/timesheets.json',
                'lang/bs/core.json',
            ]).map((p) => p.file),
        ).toEqual([
            'lang/he/core.json',
            'modules/Timesheets/lang/iw/timesheets.json',
        ]);
    });
});

describe('physical directions', () => {
    it('rejects ml-4 and suggests ms-4', () => {
        expect(
            checkDirections('resources/js/X.vue', '<div class="ml-4 p-2">'),
        ).toEqual([
            {
                file: 'resources/js/X.vue',
                line: 1,
                message: 'physical class "ml-4", use "ms-4"',
            },
        ]);
    });

    it('rejects margin-left in CSS and suggests margin-inline-start', () => {
        const [problem] = checkDirections(
            'resources/css/app.css',
            '.x {\n  margin-left: 1rem;\n}',
        );

        expect(problem.line).toBe(2);
        expect(problem.message).toContain('margin-inline-start');
    });

    it('checks style blocks and @apply in Vue files', () => {
        const lines = checkDirections(
            'resources/js/X.vue',
            '<style>\n.a { padding-right: 0; }\n.b { @apply pl-2; }\n</style>',
        ).map((p) => p.line);

        expect(lines).toEqual([2, 3]);
    });

    it('keeps variants and negative values in the suggestion', () => {
        const messages = checkDirections(
            'resources/js/X.vue',
            '<i class="dark:border-r -ml-1 md:text-left rounded-tl-sm">',
        ).map((p) => p.message);

        expect(messages).toEqual([
            'physical class "dark:border-r", use "dark:border-e"',
            'physical class "-ml-1", use "-ms-1"',
            'physical class "md:text-left", use "md:text-start"',
            'physical class "rounded-tl-sm", use "rounded-ss-sm"',
        ]);
    });

    it('accepts logical classes, look-alikes, side variants, reversed spacing and marked exceptions', () => {
        const text = [
            '<div class="ms-4 pe-2 text-start rounded-lg border-lime-500 prose">',
            '<div class="data-[side=left]:slide-in-from-right-2">',
            '<div class="flex space-x-2 rtl:space-x-reverse">',
            '<!-- i18n-physical-ok: centered with translate -->',
            '<div class="left-[50%]">',
            '/* i18n-physical-ok-begin: follows the side prop */',
            "'left-0'",
            "'right-0'",
            '/* i18n-physical-ok-end */',
        ].join('\n');

        expect(checkDirections('resources/js/X.vue', text)).toEqual([]);
    });

    it('fixes classes automatically', () => {
        expect(
            fixDirections(
                'resources/js/X.vue',
                '<div class="ml-2 text-right flex space-x-1">',
            ),
        ).toBe(
            '<div class="ms-2 text-end flex space-x-1 rtl:space-x-reverse">',
        );
    });
});

/* S-0920: directional icons mirror in right to left languages. */
describe('directional icons', () => {
    const registry = mirroredIcons(
        readFileSync('resources/css/rtl.css', 'utf8'),
    );

    it('names lucide components like their icon classes', () => {
        expect(iconName('PanelLeftClose')).toBe('panel-left-close');
        expect(iconName('Undo2')).toBe('undo-2');
        expect(iconName('ChevronRightIcon')).toBe('chevron-right');
    });

    it('accepts mirrored and non-directional icons', () => {
        const file =
            "import { ArrowLeft, ChevronRight, Clock, Search, ChevronsUpDown } from '@lucide/vue';\n";

        expect(checkIcons('a.vue', file, registry)).toEqual([]);
    });

    it('fails on a directional icon missing from the registry, with file and line', () => {
        const file =
            "import { Pencil } from '@lucide/vue';\nimport {\n    AlignLeft,\n    Clock,\n} from '@lucide/vue';\n";

        expect(checkIcons('b.vue', file, registry)).toEqual([
            {
                file: 'b.vue',
                line: 2,
                message: expect.stringContaining('.lucide-align-left'),
            },
        ]);
    });

    it('accepts a marked exception', () => {
        const file =
            "// i18n-icon-ok: aligns text, not a direction\nimport { AlignLeft } from '@lucide/vue';\n";

        expect(checkIcons('c.vue', file, registry)).toEqual([]);
    });
});

/* S-0911: no product name hard-coded in strings and templates. */
describe('brand names', () => {
    it('fails on the product or licensor name in a string or template, with file and line', () => {
        expect(
            checkBrandNames(
                'lang/en/core.json',
                '{\n  "welcome": "Welcome to Projektrack"\n}',
            ),
        ).toEqual([
            {
                file: 'lang/en/core.json',
                line: 2,
                message: expect.stringContaining('{brand}'),
            },
        ]);
        expect(checkBrandNames('a.vue', '<p>PROJEKTRA</p>')).toHaveLength(1);
    });

    it('accepts {brand}, lower-case technical names and copyright headers', () => {
        const text =
            '/*\n * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o.\n */\n"welcome": "Welcome to {brand}"\nprojektrack:strings:cache';

        expect(checkBrandNames('a.ts', text)).toEqual([]);
    });
});

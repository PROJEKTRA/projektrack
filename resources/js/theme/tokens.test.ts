/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { readdirSync, readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

/*
 * S-0910: the default theme meets WCAG 2.2 AA in both modes, all colors come
 * from tokens, and the fonts are self-hosted (docs/ux/brand.md).
 */

const read = (path: string): string => readFileSync(path, 'utf8');
const tokensCss = read('resources/css/tokens.css');
const appCss = read('resources/css/app.css');

/** Custom properties of one rule of tokens.css. */
function block(selector: string): Map<string, string> {
    const start = tokensCss.indexOf(`    ${selector} {`);
    const end = tokensCss.indexOf('\n    }', start);

    expect(start).toBeGreaterThan(-1);

    return new Map(
        [
            ...tokensCss.slice(start, end).matchAll(/(--[\w-]+):\s*([^;]+);/g),
        ].map((match) => [match[1], match[2].trim()]),
    );
}

const light = block(':root');
const modes = {
    light,
    dark: new Map([...light, ...block('.dark')]),
};

type Rgba = [number, number, number, number];

function color(tokens: Map<string, string>, name: string): Rgba {
    const value = tokens.get(name) ?? name;
    const reference = /^var\((--[\w-]+)\)$/.exec(value);

    if (reference) {
        return color(tokens, reference[1]);
    }

    const hex = /^#([0-9a-f]{6})([0-9a-f]{2})?$/i.exec(value);

    if (!hex) {
        throw new Error(`${name} is not a color: ${value}`);
    }

    const part = (index: number) =>
        parseInt(hex[1].slice(index, index + 2), 16);

    return [part(0), part(2), part(4), hex[2] ? parseInt(hex[2], 16) / 255 : 1];
}

function luminance([r, g, b]: Rgba): number {
    const [x, y, z] = [r, g, b].map((channel) => {
        const c = channel / 255;

        return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * x + 0.7152 * y + 0.0722 * z;
}

function contrast(
    tokens: Map<string, string>,
    foreground: string,
    background: string,
): number {
    const a = luminance(color(tokens, foreground));
    const b = luminance(color(tokens, background));

    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

const SURFACES = ['--background', '--card', '--popover'];

/** Text on its background: 4.5:1. */
const TEXT: [string, string][] = [
    ...SURFACES.flatMap((surface): [string, string][] =>
        [
            '--foreground',
            '--muted-foreground',
            '--heading',
            '--link',
            '--destructive',
            '--pt-success',
            '--pt-warning',
            '--pt-danger',
            '--pt-info',
        ].map((text) => [text, surface]),
    ),
    ['--foreground', '--muted'],
    ['--muted-foreground', '--muted'],
    ['--foreground', '--accent'],
    ['--primary-foreground', '--primary'],
    ['--secondary-foreground', '--secondary'],
    ['--accent-foreground', '--accent'],
    ['--destructive-foreground', '--destructive'],
    ['--sidebar-foreground', '--sidebar'],
    ['--sidebar-accent-foreground', '--sidebar-accent'],
    ['--sidebar-primary-foreground', '--sidebar-primary'],
    ...['success', 'warning', 'danger', 'info'].flatMap(
        (status): [string, string][] => [
            [`--pt-${status}`, `--pt-${status}-subtle`],
            [`--pt-on-${status}`, `--pt-${status}-solid`],
        ],
    ),
];

/** Control outlines, focus rings and charts: 3:1. */
const GRAPHICS: [string, string][] = [
    ...SURFACES.flatMap((surface): [string, string][] => [
        ['--input', surface],
        ['--ring', surface],
    ]),
    ['--sidebar-ring', '--sidebar'],
    ['--primary', '--background'],
    ['--primary', '--card'],
    ...[1, 2, 3, 4, 5, 6].map((n): [string, string] => [
        `--pt-chart-${n}`,
        '--card',
    ]),
];

describe.each(Object.entries(modes))('%s mode', (_, tokens) => {
    it.each(TEXT)('text %s on %s has 4.5:1', (text, background) => {
        expect(contrast(tokens, text, background)).toBeGreaterThanOrEqual(4.5);
    });

    it.each(GRAPHICS)('%s on %s has 3:1', (graphic, background) => {
        expect(contrast(tokens, graphic, background)).toBeGreaterThanOrEqual(3);
    });

    it('paints the page in the mode before the stylesheet arrives', () => {
        const rule = tokens === modes.dark ? 'html.dark' : 'html';
        const early = new RegExp(
            `${rule.replace('.', '\\.')} \\{\\s*background-color: (#[0-9a-f]{6});`,
        ).exec(read('resources/views/app.blade.php'));

        expect(color(tokens, early?.[1] ?? '')).toEqual(
            color(tokens, '--background'),
        );
    });
});

describe('theme', () => {
    it('leaves every token to a white-label theme: defaults sit in a cascade layer', () => {
        expect(tokensCss).toMatch(/\*\/\s*@layer theme \{/);
        expect(appCss).not.toMatch(/#[0-9a-f]{3,8}\b|oklch\(|rgba?\(/i);
    });

    it('uses tokens, never palette colors, in components', () => {
        const raw =
            /\b(?:bg|text|border|ring|fill|stroke|from|to|divide|decoration|outline)-(?:neutral|gray|zinc|slate|stone|red|green|amber|yellow|orange|blue|emerald|white|black)\b/;
        const offenders = readdirSync('resources/js', { recursive: true })
            .map(String)
            .filter((file) => /\.(vue|ts)$/.test(file))
            .filter((file) => !file.endsWith('.test.ts'))
            .filter((file) => raw.test(read(`resources/js/${file}`)));

        expect(offenders).toEqual([]);
    });

    it('serves the four fonts from the instance', () => {
        for (const font of [
            'mona-sans',
            'golos-text',
            'noto-naskh-arabic',
            'vazirmatn',
        ]) {
            expect(appCss).toContain(`@import '@fontsource-variable/${font}';`);
            expect(
                read(`node_modules/@fontsource-variable/${font}/index.css`),
            ).not.toMatch(/https?:/);
        }

        expect(appCss + tokensCss).not.toMatch(/url\(['"]?https?:/);
    });

    it('respects reduced motion and lines numbers up', () => {
        expect(appCss).toContain('(prefers-reduced-motion: reduce)');
        expect(appCss).toContain('font-variant-numeric: tabular-nums');
    });
});

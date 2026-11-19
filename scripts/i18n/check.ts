/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { parse, TYPE } from '@formatjs/icu-messageformat-parser';
import type { MessageFormatElement } from '@formatjs/icu-messageformat-parser';

/*
 * i18n quality gates (S-0917, ADR-0005 section 13). Pure functions over file
 * contents, so the same checks run in CI (cli.ts) and in unit tests.
 */

export type Problem = { file: string; line: number; message: string };

/** locale => identifier => ICU message */
export type Catalogs = Record<string, Record<string, string>>;

export const SOURCE_LOCALE = 'en';
export const REQUIRED_LOCALES = ['en', 'bs'];
export const REFUSED_LOCALES = ['he', 'iw'];
export const PSEUDO_LOCALES = ['xx', 'xx-rtl'];

const IDENTIFIER = /^[a-z][a-z0-9_]*:[a-z][a-z0-9_]*(\.[a-z0-9_]+)*$/;
const EXCEPTION = 'i18n-physical-ok:';

// ---------------------------------------------------------------- catalogs

function argumentsOf(
    elements: MessageFormatElement[],
    into = new Set<string>(),
): Set<string> {
    for (const element of elements) {
        if (
            element.type === TYPE.argument ||
            element.type === TYPE.number ||
            element.type === TYPE.date ||
            element.type === TYPE.time
        ) {
            into.add(element.value);
        } else if (
            element.type === TYPE.plural ||
            element.type === TYPE.select
        ) {
            into.add(element.value);
            for (const option of Object.values(element.options)) {
                argumentsOf(option.value, into);
            }
        } else if (element.type === TYPE.tag) {
            argumentsOf(element.children, into);
        }
    }

    return into;
}

function missingCategories(
    elements: MessageFormatElement[],
    locale: string,
): string[] {
    const missing: string[] = [];

    for (const element of elements) {
        if (element.type === TYPE.plural) {
            const required = new Intl.PluralRules(locale, {
                type: element.pluralType ?? 'cardinal',
            }).resolvedOptions().pluralCategories;
            for (const category of required) {
                if (!(category in element.options)) {
                    missing.push(`${element.value}: ${category}`);
                }
            }
        }

        if (element.type === TYPE.plural || element.type === TYPE.select) {
            for (const option of Object.values(element.options)) {
                missing.push(...missingCategories(option.value, locale));
            }
        }
    }

    return missing;
}

/**
 * Every identifier exists in en and bs, no locale has identifiers en lacks,
 * every message parses, uses only arguments the en message declares and has
 * every CLDR plural category its locale needs.
 */
export function checkCatalogs(
    catalogs: Catalogs,
    fileOf: (locale: string, identifier: string) => string,
): Problem[] {
    const problems: Problem[] = [];
    const source = catalogs[SOURCE_LOCALE] ?? {};
    const add = (locale: string, identifier: string, message: string) =>
        problems.push({
            file: fileOf(locale, identifier),
            line: 1,
            message: `${identifier} (${locale}): ${message}`,
        });

    for (const locale of REQUIRED_LOCALES) {
        for (const identifier of Object.keys(source)) {
            if (catalogs[locale]?.[identifier] === undefined) {
                add(locale, identifier, `missing in lang/${locale}`);
            }
        }
    }

    const sourceArguments = new Map<string, Set<string>>();

    for (const [locale, messages] of Object.entries(catalogs)) {
        for (const [identifier, message] of Object.entries(messages)) {
            if (
                !IDENTIFIER.test(identifier) ||
                identifier.split(':')[1].length > 100
            ) {
                add(locale, identifier, 'invalid identifier');
            }

            if (locale !== SOURCE_LOCALE && source[identifier] === undefined) {
                add(locale, identifier, 'not defined in en (orphan)');

                continue;
            }

            let ast: MessageFormatElement[];
            try {
                ast = parse(message, { ignoreTag: true });
            } catch (error) {
                add(
                    locale,
                    identifier,
                    `not valid ICU MessageFormat: ${String(error)}`,
                );

                continue;
            }

            if (locale === SOURCE_LOCALE) {
                sourceArguments.set(identifier, argumentsOf(ast));
            }

            for (const missing of missingCategories(ast, locale)) {
                add(
                    locale,
                    identifier,
                    `plural lacks the category required in ${locale}: ${missing}`,
                );
            }
        }
    }

    for (const [locale, messages] of Object.entries(catalogs)) {
        if (locale === SOURCE_LOCALE) {
            continue;
        }

        for (const [identifier, message] of Object.entries(messages)) {
            const declared = sourceArguments.get(identifier);
            if (!declared) {
                continue;
            }

            try {
                for (const name of argumentsOf(
                    parse(message, { ignoreTag: true }),
                )) {
                    if (!declared.has(name)) {
                        add(
                            locale,
                            identifier,
                            `uses argument {${name}} that the en message does not declare`,
                        );
                    }
                }
            } catch {
                // Reported above.
            }
        }
    }

    return problems;
}

/** Language folders that must never exist (ADR-0005: no Hebrew). */
export function checkRefusedLocales(paths: string[]): Problem[] {
    return paths
        .filter((path) =>
            REFUSED_LOCALES.some((locale) =>
                new RegExp(`(^|/)lang/${locale}(/|$)`).test(path),
            ),
        )
        .map((path) => ({
            file: path,
            line: 1,
            message: 'Hebrew locale files are not allowed',
        }));
}

// ---------------------------------------------------------------- usage

const USAGE =
    /(?:\b__|\btrans|\$t|\bt|->get)\(\s*['"`]([a-z][a-z0-9_]*:[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)*)['"`]/g;

/** Identifiers used in source code that are missing in en or bs. */
export function checkUsage(
    file: string,
    text: string,
    catalogs: Catalogs,
    components: Set<string>,
): Problem[] {
    const problems: Problem[] = [];

    for (const match of text.matchAll(USAGE)) {
        const identifier = match[1];
        if (!components.has(identifier.split(':')[0])) {
            continue;
        }

        for (const locale of REQUIRED_LOCALES) {
            if (catalogs[locale]?.[identifier] === undefined) {
                problems.push({
                    file,
                    line: text.slice(0, match.index).split('\n').length,
                    message: `${identifier} is used here but missing in lang/${locale}`,
                });
            }
        }
    }

    return problems;
}

// ---------------------------------------------------------------- directions

/** Physical Tailwind utility prefix => logical replacement. Longest first. */
const UTILITIES: [string, string][] = [
    ['scroll-ml-', 'scroll-ms-'],
    ['scroll-mr-', 'scroll-me-'],
    ['scroll-pl-', 'scroll-ps-'],
    ['scroll-pr-', 'scroll-pe-'],
    ['rounded-tl', 'rounded-ss'],
    ['rounded-tr', 'rounded-se'],
    ['rounded-bl', 'rounded-es'],
    ['rounded-br', 'rounded-ee'],
    ['rounded-l', 'rounded-s'],
    ['rounded-r', 'rounded-e'],
    ['border-l', 'border-s'],
    ['border-r', 'border-e'],
    ['text-left', 'text-start'],
    ['text-right', 'text-end'],
    ['float-left', 'float-start'],
    ['float-right', 'float-end'],
    ['clear-left', 'clear-start'],
    ['clear-right', 'clear-end'],
    ['slide-in-from-left', 'slide-in-from-start'],
    ['slide-in-from-right', 'slide-in-from-end'],
    ['slide-out-to-left', 'slide-out-to-start'],
    ['slide-out-to-right', 'slide-out-to-end'],
    ['space-x-', 'gap-x- (or add rtl:space-x-reverse)'],
    ['divide-x', 'a logical border (or add rtl:divide-x-reverse)'],
    ['left-', 'start-'],
    ['right-', 'end-'],
    ['ml-', 'ms-'],
    ['mr-', 'me-'],
    ['pl-', 'ps-'],
    ['pr-', 'pe-'],
];

/** Physical CSS property => logical replacement. */
const PROPERTIES: [RegExp, string][] = [
    [/\bmargin-left\s*:/, 'margin-inline-start'],
    [/\bmargin-right\s*:/, 'margin-inline-end'],
    [/\bpadding-left\s*:/, 'padding-inline-start'],
    [/\bpadding-right\s*:/, 'padding-inline-end'],
    [/\bborder-left(-\w+)?\s*:/, 'border-inline-start'],
    [/\bborder-right(-\w+)?\s*:/, 'border-inline-end'],
    [
        /\bborder-(top|bottom)-(left|right)-radius\s*:/,
        'border-start-start-radius and similar',
    ],
    [/(^|[;{\s])left\s*:/, 'inset-inline-start'],
    [/(^|[;{\s])right\s*:/, 'inset-inline-end'],
    [/\btext-align\s*:\s*(left|right)\b/, 'text-align: start or end'],
    [/\bfloat\s*:\s*(left|right)\b/, 'float: inline-start or inline-end'],
];

/** The utility of a class token without variants, important and negative marks. */
function utilityOf(
    token: string,
): { utility: string; variants: string[] } | null {
    const parts: string[] = [];
    let depth = 0;
    let current = '';

    for (const char of token) {
        if (char === '[') depth++;
        if (char === ']') depth--;
        if (char === ':' && depth === 0) {
            parts.push(current);
            current = '';
        } else {
            current += char;
        }
    }

    const utility = current
        .replace(/^!/, '')
        .replace(/^-/, '')
        .replace(/!$/, '');

    return /^[a-z]/.test(utility) ? { utility, variants: parts } : null;
}

function suggestion(utility: string): string | null {
    for (const [physical, logical] of UTILITIES) {
        // "ml-" is a prefix; "border-l" is a whole utility or followed by "-<value>".
        const matches = physical.endsWith('-')
            ? utility.startsWith(physical)
            : utility === physical || utility.startsWith(`${physical}-`);

        if (matches) {
            return logical.includes(' ')
                ? logical
                : logical + utility.slice(physical.length);
        }
    }

    return null;
}

/** Quoted strings of a line (class attributes and class strings in scripts). */
function quoted(line: string): string[] {
    return [...line.matchAll(/"([^"]*)"|'([^']*)'|`([^`]*)`/g)].map(
        (m) => m[1] ?? m[2] ?? m[3] ?? '',
    );
}

type Finding = {
    line: number;
    message: string;
    token?: string;
    replacement?: string;
};

function findings(file: string, text: string): Finding[] {
    const found: Finding[] = [];
    const lines = text.split('\n');
    const isCss = file.endsWith('.css');
    let inStyle = false;
    let inException = false;

    lines.forEach((line, index) => {
        if (line.includes(`${EXCEPTION.slice(0, -1)}-begin:`))
            inException = true;
        if (line.includes(`${EXCEPTION.slice(0, -1)}-end`)) {
            inException = false;

            return;
        }
        if (
            inException ||
            line.includes(EXCEPTION) ||
            (index > 0 && lines[index - 1].includes(EXCEPTION))
        ) {
            return;
        }

        if (/<style[\s>]/.test(line)) inStyle = true;
        if (/<\/style>/.test(line)) inStyle = false;

        if (isCss || inStyle) {
            for (const [pattern, logical] of PROPERTIES) {
                const match = line.match(pattern);
                if (match) {
                    found.push({
                        line: index + 1,
                        message: `physical CSS "${match[0].trim()}", use ${logical}`,
                    });
                }
            }
        }

        const classSources =
            isCss || inStyle
                ? [...line.matchAll(/@apply\s+([^;]+)/g)].map((m) => m[1])
                : quoted(line);

        for (const source of classSources) {
            const tokens = source.split(/\s+/);

            for (const token of tokens) {
                const parsed = utilityOf(token);
                if (!parsed) continue;
                if (
                    parsed.variants.some((v) =>
                        /^(rtl|ltr)$|side=(left|right)|^\[dir=/.test(v),
                    )
                )
                    continue;

                const logical = suggestion(parsed.utility);
                if (!logical) continue;

                // space-x and divide-x are fine once they are reversed in RTL.
                if (logical.includes(' ')) {
                    const reverse = parsed.utility.startsWith('space-x')
                        ? 'rtl:space-x-reverse'
                        : 'rtl:divide-x-reverse';
                    if (!tokens.some((t) => t.endsWith(reverse))) {
                        found.push({
                            line: index + 1,
                            message: `physical class "${token}", use ${logical}`,
                            token,
                            replacement: `${token} ${[...parsed.variants, reverse].join(':')}`,
                        });
                    }

                    continue;
                }

                const at = token.lastIndexOf(parsed.utility);
                const replacement =
                    token.slice(0, at) +
                    logical +
                    token.slice(at + parsed.utility.length);
                found.push({
                    line: index + 1,
                    message: `physical class "${token}", use "${replacement}"`,
                    token,
                    replacement,
                });
            }
        }
    });

    return found;
}

/**
 * Physical directions in Tailwind classes and CSS. Allowed: positioning
 * variants named after a physical side (data-[side=left]:...), explicit
 * rtl:/ltr: handling, and code marked "i18n-physical-ok: <reason>" (that
 * line and the next) or wrapped in "i18n-physical-ok-begin: <reason>" and
 * "i18n-physical-ok-end".
 */
export function checkDirections(file: string, text: string): Problem[] {
    return findings(file, text).map(({ line, message }) => ({
        file,
        line,
        message,
    }));
}

/** Replace physical Tailwind classes by their logical counterparts. */
export function fixDirections(file: string, text: string): string {
    const lines = text.split('\n');

    for (const { line, token, replacement } of findings(file, text)) {
        if (token && replacement) {
            const escaped = token.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&');
            lines[line - 1] = lines[line - 1].replace(
                new RegExp(`(^|[\\s"'\`])${escaped}(?=[\\s"'\`]|$)`),
                `$1${replacement}`,
            );
        }
    }

    return lines.join('\n');
}

// ------------------------------------------------------------------- icons

const ICON_EXCEPTION = 'i18n-icon-ok:';
/** Icon names that point along the reading direction (S-0920). */
const DIRECTIONAL_ICON =
    /(^|-)(left|right|undo|redo|log-in|log-out|reply|forward|indent)(-|$)/;

/** Lucide component name to its icon name: PanelLeftClose => panel-left-close. */
export function iconName(component: string): string {
    return component
        .replace(/Icon$/, '')
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/([a-zA-Z])([0-9])/g, '$1-$2')
        .toLowerCase();
}

/** Icon names mirrored by resources/css/rtl.css (".lucide-<name>"). */
export function mirroredIcons(css: string): Set<string> {
    return new Set(
        [...css.matchAll(/\.lucide-([a-z0-9-]+)/g)].map((match) => match[1]),
    );
}

/**
 * Directional icons imported from @lucide/vue must be mirrored in right to
 * left languages, so they must be listed in resources/css/rtl.css. Icons that
 * look directional but are not (a text alignment, for example) carry
 * "i18n-icon-ok: <reason>" on the import line or the line before.
 */
export function checkIcons(
    file: string,
    text: string,
    mirrored: Set<string>,
): Problem[] {
    const problems: Problem[] = [];
    const lines = text.split('\n');
    const imports = /import\s*\{([^}]*)\}\s*from\s*['"]@lucide\/vue['"]/g;

    for (const match of text.matchAll(imports)) {
        const start = text.slice(0, match.index).split('\n').length;
        const names = match[1].split(',').map((part) =>
            part
                .trim()
                .split(/\s+as\s+/)[0]
                .trim(),
        );
        const end = start + match[0].split('\n').length - 1;
        const marked = lines
            .slice(Math.max(0, start - 2), end)
            .some((line) => line.includes(ICON_EXCEPTION));

        if (marked) continue;

        for (const name of names.filter(Boolean)) {
            const icon = iconName(name);
            if (DIRECTIONAL_ICON.test(icon) && !mirrored.has(icon)) {
                problems.push({
                    file,
                    line: start,
                    message: `directional icon "${name}" is not mirrored in right to left languages: add .lucide-${icon} to resources/css/rtl.css, or mark the import with "${ICON_EXCEPTION} <reason>"`,
                });
            }
        }
    }

    return problems;
}

// ------------------------------------------------------------------- brand

/** Names that only config/brand.php and config/licensor.php may contain (S-0911). */
const BRAND_NAMES = /\b(Projektrack|PROJEKTRA)\b/;

/**
 * No product or licensor name in strings and templates: strings use {brand},
 * templates read the brand of the tenant (App\Branding\Brand). Copyright
 * headers are allowed.
 */
export function checkBrandNames(file: string, text: string): Problem[] {
    const problems: Problem[] = [];

    text.split('\n').forEach((line, index) => {
        if (/Copyright|SPDX-License-Identifier/.test(line)) {
            return;
        }

        const match = line.match(BRAND_NAMES);
        if (match) {
            problems.push({
                file,
                line: index + 1,
                message: `product name "${match[1]}" written into ${file.endsWith('.json') ? 'a string' : 'a template'}: use {brand} in strings, the brand prop or App\\Branding\\Brand in templates`,
            });
        }
    });

    return problems;
}

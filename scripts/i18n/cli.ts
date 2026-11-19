/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import {
    existsSync,
    readdirSync,
    readFileSync,
    statSync,
    writeFileSync,
} from 'node:fs';
import { basename, join, relative } from 'node:path';
import {
    checkBrandNames,
    checkCatalogs,
    checkDirections,
    checkIcons,
    checkRefusedLocales,
    checkUsage,
    fixDirections,
    mirroredIcons,
    PSEUDO_LOCALES,
} from './check.ts';
import type { Catalogs, Problem } from './check.ts';

/*
 * Runs the i18n quality gates over the repository (S-0917):
 *   node scripts/i18n/cli.ts      (npm run lint:i18n, part of npm run lint)
 * Exits with 1 and prints file:line for every problem.
 */

const ROOT = process.cwd();
// --fix rewrites physical Tailwind classes to logical ones.
const FIX = process.argv.includes('--fix');
const SKIP =
    /(^|\/)(node_modules|vendor|public|storage|bootstrap\/cache|resources\/js\/(actions|routes|wayfinder))(\/|$)/;

function walk(dir: string, files: string[] = []): string[] {
    if (!existsSync(dir)) {
        return files;
    }

    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        const rel = relative(ROOT, path).replaceAll('\\', '/');
        if (SKIP.test(rel)) continue;

        if (statSync(path).isDirectory()) {
            walk(path, files);
        } else {
            files.push(rel);
        }
    }

    return files;
}

const moduleDirs = ['modules', 'ee/modules']
    .filter((dir) => existsSync(dir))
    .flatMap((dir) => readdirSync(dir).map((name) => `${dir}/${name}`))
    .filter((dir) => existsSync(`${dir}/module.json`));

const components = new Set(['core']);
const catalogs: Catalogs = {};
const origin = new Map<string, string>();

function load(file: string, component: string, locale: string): void {
    const data = JSON.parse(readFileSync(file, 'utf8')) as Record<
        string,
        string
    >;
    catalogs[locale] ??= {};

    for (const [key, message] of Object.entries(data)) {
        catalogs[locale][`${component}:${key}`] = message;
        origin.set(`${locale}\u0000${component}:${key}`, file);
    }
}

const langFiles = walk('lang');
for (const file of langFiles.filter((f) =>
    /^lang\/[^/]+\/[^/]+\.json$/.test(f),
)) {
    const locale = file.split('/')[1];
    if (!PSEUDO_LOCALES.includes(locale)) {
        load(file, basename(file, '.json'), locale);
    }
}

const moduleLangFiles: string[] = [];
for (const dir of moduleDirs) {
    const manifest = JSON.parse(readFileSync(`${dir}/module.json`, 'utf8')) as {
        key: string;
    };
    components.add(manifest.key);

    for (const file of walk(`${dir}/lang`)) {
        moduleLangFiles.push(file);
        const match = file.match(/\/lang\/([^/]+)\/([^/]+)\.json$/);
        if (match && match[2] === manifest.key) {
            load(file, manifest.key, match[1]);
        }
    }
}

const problems: Problem[] = [
    ...checkRefusedLocales([...langFiles, ...moduleLangFiles]),
    // No product name in any string (S-0911).
    ...[...langFiles, ...moduleLangFiles]
        .filter((file) => file.endsWith('.json'))
        .flatMap((file) => checkBrandNames(file, readFileSync(file, 'utf8'))),
    ...checkCatalogs(
        catalogs,
        (locale, identifier) =>
            origin.get(`${locale}\u0000${identifier}`) ?? `lang/${locale}`,
    ),
];

const sources = [
    ...walk('app'),
    ...walk('resources'),
    ...walk('routes'),
    ...moduleDirs.flatMap((dir) => walk(dir)),
].filter(
    (file) =>
        /\.(php|vue|ts|css)$/.test(file) &&
        !/\.test\.ts$/.test(file) &&
        !/\.d\.ts$/.test(file),
);

// Directional icons mirrored in right to left languages (S-0920).
const mirrored = mirroredIcons(
    existsSync('resources/css/rtl.css')
        ? readFileSync('resources/css/rtl.css', 'utf8')
        : '',
);

for (const file of sources) {
    const text = readFileSync(file, 'utf8');

    if (/\.(vue|ts)$/.test(file)) {
        problems.push(...checkIcons(file, text, mirrored));
    }

    // No product name in templates (S-0911).
    if (
        /^(resources|modules|ee)\//.test(file) &&
        /\.(vue|ts|blade\.php)$/.test(file)
    ) {
        problems.push(...checkBrandNames(file, text));
    }

    if (/\.(php|vue|ts)$/.test(file)) {
        problems.push(...checkUsage(file, text, catalogs, components));
    }

    if (
        /^(resources|modules|ee)\//.test(file) &&
        /\.(vue|ts|css|blade\.php)$/.test(file)
    ) {
        if (FIX) {
            const fixed = fixDirections(file, text);
            if (fixed !== text) {
                writeFileSync(file, fixed);
            }
        }

        problems.push(
            ...checkDirections(file, FIX ? readFileSync(file, 'utf8') : text),
        );
    }
}

for (const problem of problems) {
    if (process.env.GITHUB_ACTIONS) {
        console.log(
            `::error file=${problem.file},line=${problem.line}::${problem.message}`,
        );
    }
    console.log(`${problem.file}:${problem.line}  ${problem.message}`);
}

const count = Object.keys(catalogs.en ?? {}).length;
console.log(
    problems.length === 0
        ? `i18n: ${count} strings, ${sources.length} files checked, no problems.`
        : `i18n: ${problems.length} problem(s).`,
);

process.exit(problems.length === 0 ? 0 : 1);

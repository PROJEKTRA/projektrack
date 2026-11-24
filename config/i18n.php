<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * String system and locales (S-0916, ADR-0005).
 */
return [

    // Locales users and tenants can choose. A locale is offered only when its
    // language files exist; more arrive with language packs (S-0921, S-0922).
    'locales' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('I18N_LOCALES', 'bs,en,de')),
    ))),

    // Default for a new installation and the first fallback after the user's
    // and the tenant's language.
    'default' => env('APP_LOCALE', 'bs'),

    // Technical source language: every identifier exists here (CI rule), so it
    // is always the last link of the chain.
    'source' => 'en',

    // Written right to left (ADR-0005 section 7).
    'rtl' => ['ar', 'fa', 'xx-rtl'],

    // Pseudo-locales xx and xx-rtl for testers (S-0917): on in development,
    // CI and demo instances, off in production unless an operator enables them.
    'pseudo' => (bool) env('I18N_PSEUDO', env('APP_ENV', 'production') !== 'production'),

    // Regional formats users can choose, separate from the language
    // (S-0919, ADR-0005 section 3): dates, numbers, currency amounts.
    'formats' => ['bs-BA', 'hr-HR', 'sr-Latn-BA', 'de-DE', 'de-AT', 'de-CH', 'en-GB', 'en-US', 'fr-FR', 'es-ES', 'ar-SA', 'fa-IR'],

    // Regional format when the user chose none: the usual one of their language.
    'default_formats' => ['bs' => 'bs-BA', 'hr' => 'hr-HR', 'sr' => 'sr-Latn-BA', 'de' => 'de-DE', 'en' => 'en-GB', 'fr' => 'fr-FR', 'es' => 'es-ES', 'ar' => 'ar-SA', 'fa' => 'fa-IR', 'xx' => 'en-GB', 'xx-rtl' => 'en-GB'],

    // Numeric date patterns that replace the CLDR pattern of a language
    // (product decision S-0919: 01.11.2026 instead of CLDR's 01. 11. 2026.).
    // Tokens: dd, d, MM, M, yyyy, yy; anything else is kept literally.
    'date_patterns' => ['bs' => 'dd.MM.yyyy'],

    // Never shipped, offered or accepted (ADR-0005).
    'refused' => ['he', 'iw'],

    // Compiled strings per locale (OPcache friendly). Refreshed by
    // `php artisan optimize` and projektrack:strings:cache.
    'cache_path' => env('I18N_CACHE_PATH', base_path('bootstrap/cache/strings')),

];

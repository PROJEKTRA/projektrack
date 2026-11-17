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
        explode(',', (string) env('I18N_LOCALES', 'bs,en')),
    ))),

    // Default for a new installation and the first fallback after the user's
    // and the tenant's language.
    'default' => env('APP_LOCALE', 'bs'),

    // Technical source language: every identifier exists here (CI rule), so it
    // is always the last link of the chain.
    'source' => 'en',

    // Written right to left (ADR-0005 section 7).
    'rtl' => ['ar', 'fa'],

    // Never shipped, offered or accepted (ADR-0005).
    'refused' => ['he', 'iw'],

    // Compiled strings per locale (OPcache friendly). Refreshed by
    // `php artisan optimize` and projektrack:strings:cache.
    'cache_path' => env('I18N_CACHE_PATH', base_path('bootstrap/cache/strings')),

];

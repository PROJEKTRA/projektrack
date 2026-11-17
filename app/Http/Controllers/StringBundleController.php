<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers;

use App\I18n\Locales;
use App\I18n\Strings;
use Illuminate\Http\JsonResponse;

/**
 * Serves the resolved strings for one locale chain as a versioned bundle
 * (S-0916). A bundle for the current version never changes, so the browser
 * may keep it; an outdated version is answered with the current strings and
 * must not be cached.
 *
 * Shipped strings are not secret: the route is public so sign-in pages can use
 * it, and it runs without a session.
 */
final class StringBundleController
{
    public function __invoke(string $chain, string $version, Locales $locales, Strings $strings): JsonResponse
    {
        $requested = explode('.', $chain);

        foreach ($requested as $locale) {
            abort_unless($locales->isAvailable($locale) && Locales::normalize($locale) === $locale, 404);
        }

        $canonical = $locales->chain(...$requested);
        abort_unless($canonical === $requested, 404);

        $bundle = $strings->bundle($canonical);
        $current = $bundle['version'] === $version;

        return response()
            ->json($bundle, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Cache-Control', $current ? 'private, max-age=31536000, immutable' : 'no-store');
    }
}

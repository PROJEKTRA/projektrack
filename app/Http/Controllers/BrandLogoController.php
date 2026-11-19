<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers;

use App\Branding\Brand;
use App\Files\FileStore;
use App\Models\StoredFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The tenant's logo (S-0911), also on the sign-in page. Public, because the
 * brand is shown before sign-in; only the current logo of the host's tenant
 * is served, never another file.
 */
class BrandLogoController extends Controller
{
    public function __invoke(Brand $brand, FileStore $files, string $version): StreamedResponse
    {
        $current = $brand->current()['logoFileId'];
        abort_unless($current !== null && hash_equals($current, $version), 404);

        $logo = StoredFile::query()->findOrFail($current);
        // A generic name: the uploaded file name is not for anonymous visitors.
        $response = $files->download($logo, inline: true, name: 'logo');
        // The URL changes with every new logo; a day keeps a removed one from lingering in caches.
        $response->headers->set('Cache-Control', 'public, max-age=86400');

        return $response;
    }
}

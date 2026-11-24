<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers;

use App\Authorization\Permissions;
use App\Branding\Brand;
use App\Branding\Licensor;
use App\I18n\Strings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * About (S-0911): the brand the user sees, the version and the licensor.
 * Only for signed-in users: before sign-in nothing reveals the software.
 */
class AboutController extends Controller
{
    /** What 0.9 brings and what it does not do yet (docs/release-notes). */
    private const array NEWS = [
        'new' => ['core:about.new.customers', 'core:about.new.time', 'core:about.new.reports', 'core:about.new.invoices', 'core:about.new.einvoices', 'core:about.new.languages'],
        'limits' => ['core:about.limit.legal', 'core:about.limit.validators', 'core:about.limit.missing'],
    ];

    public function __invoke(Brand $brand, Licensor $licensor): Response
    {
        return Inertia::render('About', [
            'product' => $brand->name(),
            // The version only for administrators (INV-10).
            'version' => app(Permissions::class)->allows($this->currentUser(request()), 'core.admin') ? config('app.version') : null,
            'news' => [
                'title' => __('core:about.news'), 'limits_title' => __('core:about.limits'),
                ...array_map(fn (array $ids): array => array_map(fn (string $id): string => app(Strings::class)->get($id), $ids), self::NEWS),
            ],
            // The endorsement line names the shipped product and its licensor (docs/ux/brand.md).
            'poweredBy' => $brand->current()['poweredBy']
                ? ['product' => config()->string('brand.name'), 'licensor' => config()->string('licensor.short_name')]
                : null,
            'licensor' => $licensor->details(),
        ]);
    }
}

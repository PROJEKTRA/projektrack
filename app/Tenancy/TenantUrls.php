<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Closure;
use Illuminate\Support\Facades\URL;

/**
 * Links in e-mails lead to the recipient's tenant (S-0911): its primary
 * host, not APP_URL. A queue worker has no request host, so without this a
 * link would open the instance host, where the tenant (and its reset token)
 * is not found.
 */
final class TenantUrls
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** https://<primary host of the tenant>, or null when it has none. */
    public function root(Tenant $tenant): ?string
    {
        $host = $this->tenants->run($tenant, fn (): mixed => TenantDomain::query()
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->value('host'));

        if (! is_string($host) || $host === '') {
            return null;
        }

        $app = config()->string('app.url');
        $scheme = parse_url($app, PHP_URL_SCHEME) ?: 'https';
        // A port in APP_URL (development) applies to every host of the instance.
        $port = parse_url($app, PHP_URL_PORT);

        return "{$scheme}://{$host}".(is_int($port) ? ":{$port}" : '');
    }

    /**
     * Run with URLs rooted at the tenant's host.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function within(Tenant $tenant, Closure $callback): mixed
    {
        $root = $this->root($tenant);

        if ($root === null) {
            return $callback();
        }

        URL::forceRootUrl($root);

        try {
            return $callback();
        } finally {
            URL::forceRootUrl(null);
        }
    }
}

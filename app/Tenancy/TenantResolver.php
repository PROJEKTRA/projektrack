<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;

/**
 * Finds the tenant for a request host (ADR-0006, decision 4). This is the
 * documented adapter that may read tenant_domains without a tenant context.
 */
final class TenantResolver
{
    public function forHost(string $host): ?Tenant
    {
        $host = strtolower(rtrim($host, '.'));

        if ($host === '') {
            return null;
        }

        $domain = TenantDomain::withoutGlobalScope(TenantScope::class)
            ->where('host', $host)
            ->first();

        if ($domain === null) {
            return null;
        }

        $tenant = Tenant::query()->find($domain->tenant_id);

        return $tenant !== null && $tenant->isActive() ? $tenant : null;
    }
}

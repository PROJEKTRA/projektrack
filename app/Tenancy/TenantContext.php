<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * The one place that knows which tenant the current request, job, command or
 * scheduled task works for (ADR-0006, decision 2). Tenant-owned queries refuse
 * to run while no tenant is set.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * @throws MissingTenantContext
     */
    public function current(): Tenant
    {
        return $this->tenant ?? throw new MissingTenantContext;
    }

    /**
     * @throws MissingTenantContext
     */
    public function id(): string
    {
        return $this->current()->id;
    }

    /**
     * Run $callback for $tenant and restore the previous context afterwards,
     * also when the callback throws.
     *
     * @template T
     *
     * @param  Closure(Tenant): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Synthetic tenants only (project rule: no real company data outside production).
 *
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => $name,
            'status' => 'active',
            'country_code' => 'BA',
            'default_locale' => 'bs',
            'base_currency' => 'BAM',
            'timezone' => 'Europe/Sarajevo',
            'region' => 'eu',
            'edition' => 'community',
        ];
    }

    /**
     * Serve the tenant at $host.
     */
    public function withDomain(string $host): static
    {
        return $this->afterCreating(function (Tenant $tenant) use ($host): void {
            app(TenantContext::class)->run($tenant, fn () => TenantDomain::query()->create([
                'host' => $host,
                'kind' => 'custom',
                'is_primary' => true,
                'verified_at' => now(),
            ]));
        });
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Database\Seeders;

use App\Authorization\RoleManager;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local development data only: one synthetic tenant served at the host of
 * APP_URL and one verified user. Never run against production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The development seeder must not run in production.');
        }

        $url = config('app.url');
        $host = (is_string($url) ? parse_url($url, PHP_URL_HOST) : null) ?: 'localhost';

        $tenant = Tenant::factory()->withDomain($host)->create([
            'slug' => 'demo-company',
            'name' => 'Demo Company d.o.o.',
        ]);

        app(TenantContext::class)->run($tenant, function (): void {
            $admin = User::factory()->create([
                'name' => 'Demo Admin',
                'email' => 'admin@example.test',
            ]);

            app(RoleManager::class)->assign($admin, Role::query()->where('key', Role::ADMIN)->firstOrFail());
        });
    }
}

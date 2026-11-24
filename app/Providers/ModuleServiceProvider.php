<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Dashboard\Dashboard;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Modules\TenantModules;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Loads the module registry and registers each valid module's service provider
 * in dependency order. Per-tenant enable state is applied on top of this once
 * tenancy exists (S-0902); a module that is installed but disabled for a
 * tenant serves nothing to that tenant.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn (): ModuleRegistry => new ModuleRegistry(
            [base_path('modules'), base_path('ee/modules')],
            $this->app->make(LoggerInterface::class),
            config('licensing.vendor_instance') === true,
        ));

        $this->app->singleton(Dashboard::class, function (): Dashboard {
            $dashboard = new Dashboard;
            $dashboard->step('core', 'core:dashboard.step.team', fn (): string => route('admin.users.index', absolute: false), fn (): bool => User::query()->count() > 1);

            return $dashboard;
        });

        // Scoped: the per-tenant cache lives for one request or job only.
        $this->app->scoped(TenantModules::class);

        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            $studly = Str::studly($module->key);
            $provider = "Modules\\{$studly}\\{$studly}ServiceProvider";

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}

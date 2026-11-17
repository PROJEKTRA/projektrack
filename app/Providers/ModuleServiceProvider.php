<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

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
        ));

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

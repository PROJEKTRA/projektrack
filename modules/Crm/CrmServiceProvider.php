<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm;

use Illuminate\Support\ServiceProvider;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Crm\Domain\Directory;

/**
 * Customers and contacts (S-0939).
 */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompanyDirectory::class, Directory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
    }
}

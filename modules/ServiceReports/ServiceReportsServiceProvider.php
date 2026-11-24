<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\ServiceReports;

use App\Dashboard\Dashboard;
use Illuminate\Support\ServiceProvider;
use Modules\ServiceReports\Domain\ServiceReport;

/**
 * Service reports with frozen content, as PDF and CSV (S-0953, S-0954, S-0957).
 */
class ServiceReportsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->app->make(Dashboard::class)->step('service_reports', 'service_reports:dashboard.step', fn (): string => route('service_reports.index', absolute: false), fn (): bool => ServiceReport::query()->exists());
        $this->loadViewsFrom(__DIR__.'/resources/views', 'service_reports');
    }
}

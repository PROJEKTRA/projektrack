<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets;

use Illuminate\Support\ServiceProvider;
use Modules\Projects\Contracts\BookedTime;
use Modules\Timesheets\Contracts\ApprovedTime;
use Modules\Timesheets\Domain\ApprovedEntries;
use Modules\Timesheets\Domain\EntryTotals;

/**
 * Time tracking (S-0944, S-0945, S-0947, S-0948).
 */
class TimesheetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BookedTime::class, EntryTotals::class);
        $this->app->bind(ApprovedTime::class, ApprovedEntries::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
    }
}

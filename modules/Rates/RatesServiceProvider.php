<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates;

use App\Dashboard\Dashboard;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Rates\Contracts\TimeValues;
use Modules\Rates\Domain\RateRule;
use Modules\Rates\Domain\Rates;
use Modules\Rates\Domain\StoredValues;
use Modules\Timesheets\Contracts\TimeApproved;

/**
 * Hourly rates and the value of approved time (S-0950).
 */
class RatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TimeValues::class, StoredValues::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->app->make(Dashboard::class)->step('rates', 'rates:dashboard.step', fn (): string => route('rates.index', absolute: false), fn (): bool => RateRule::query()->exists());
        Event::listen(TimeApproved::class, fn (TimeApproved $event) => $this->app->make(Rates::class)->value($event));
    }
}

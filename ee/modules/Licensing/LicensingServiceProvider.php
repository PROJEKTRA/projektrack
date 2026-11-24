<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

namespace Modules\Licensing;

use App\Modules\TenantModules;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Contracts\InvoicePaid;
use Modules\Licensing\Console\OverdueOrders;
use Modules\Licensing\Domain\Orders;

/**
 * Plans and direct sale of licenses, on the licensor's own instance (S-0973,
 * S-0975). Licenses are signed offline; no private key ever comes here.
 */
class LicensingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->commands([OverdueOrders::class]);
        Event::listen(InvoicePaid::class, fn (InvoicePaid $event) => $this->app->make(TenantModules::class)->isEnabled('licensing') ? $this->app->make(Orders::class)->paid($event->invoiceId) : null);
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command(OverdueOrders::class)->dailyAt('06:00'));
    }
}

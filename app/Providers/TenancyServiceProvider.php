<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantPasswordBrokerManager;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // The framework registers its broker manager lazily (deferred provider). Load it
        // now, once the deferred list is known, so ours replaces it for good.
        $this->app->loadDeferredProvider('auth.password');
        $this->app->singleton('auth.password', fn (): TenantPasswordBrokerManager => new TenantPasswordBrokerManager($this->app));

        // A long-running queue worker must never carry one job's tenant into the next.
        Event::listen(JobProcessing::class, fn () => $this->app->make(TenantContext::class)->forget());
        Event::listen(JobProcessed::class, fn () => $this->app->make(TenantContext::class)->forget());
    }
}

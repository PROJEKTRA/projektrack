<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Audit\AuditLog;
use App\Audit\AuditSubscriber;
use App\Audit\ChainKeys;
use App\Audit\ChainVerifier;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Tamper-evident audit log (S-0907).
 */
class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChainKeys::class, fn (): ChainKeys => ChainKeys::fromConfig());
        $this->app->singleton(ChainVerifier::class);

        // Per request or job, so one correlation id groups the entries of one action.
        $this->app->scoped(AuditLog::class);
    }

    public function boot(): void
    {
        Event::subscribe(AuditSubscriber::class);
    }
}

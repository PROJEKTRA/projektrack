<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Auth\NotCommonPassword;
use App\Branding\Brand;
use App\Branding\SetMailSender;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Settings of the current tenant, read once per request (S-0912).
        $this->app->scoped(Settings::class);
        // Brand of the current tenant (S-0911).
        $this->app->scoped(Brand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Sender name of the tenant's brand on every e-mail (S-0911).
        Event::listen(MessageSending::class, SetMailSender::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Password policy (S-0903, security baseline section 2): length over
        // composition rules, a bundled list of common passwords, and the
        // breached-password check only when an operator switches it on,
        // because it is a request to an outside service.
        Password::defaults(function (): Password {
            $rule = Password::min(12)->max(128)->rules([new NotCommonPassword]);

            return config('auth.password_breach_check') === true ? $rule->uncompromised() : $rule;
        });
    }
}

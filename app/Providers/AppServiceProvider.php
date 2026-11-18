<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Auth\NotCommonPassword;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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

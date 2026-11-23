<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Auth\NotCommonPassword;
use App\Branding\Brand;
use App\Branding\SetMailSender;
use App\Models\ApiToken;
use App\Models\User;
use App\Settings\Settings;
use App\SharedLinks\SharedLinks;
use App\SharedLinks\SharedLinkTargets;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Settings of the current tenant, read once per request (S-0912).
        $this->app->scoped(Settings::class);
        // Shareable object types; modules register theirs (S-0987).
        $this->app->singleton(SharedLinkTargets::class);
        // Brand of the current tenant (S-0911).
        $this->app->scoped(Brand::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // REST API (S-0913): tokens in their own tenant-scoped table, 60 requests a minute per token.
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);
        RateLimiter::for('api', function (Request $request): array {
            $user = $request->user();
            $token = $user instanceof User ? $user->currentAccessToken() : null;

            return [
                Limit::perMinute(60)->by($token instanceof ApiToken ? 'token:'.$token->id : 'ip:'.$request->ip()),
                // More tokens do not mean more requests.
                Limit::perMinute(120)->by($user instanceof User ? 'user:'.$user->id : 'ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('api-ip', fn (Request $request): Limit => Limit::perMinute(120)->by('ip:'.$request->ip()));

        // Shared links (S-0987): per address and per link (by the hash, never the token).
        RateLimiter::for('shared-links', function (Request $request): array {
            $token = $request->route('token');

            return [
                Limit::perMinute(60)->by('shared-ip:'.SharedLinks::limitKey($request->ip())),
                Limit::perMinute(120)->by('shared-link:'.SharedLinks::hash(is_string($token) ? $token : '')),
            ];
        });
        RateLimiter::for('shared-links-unlock', function (Request $request): array {
            $token = $request->route('token');

            return [
                Limit::perMinute(10)->by('shared-unlock-ip:'.SharedLinks::limitKey($request->ip())),
                // The lock allows 10 wrong passwords an hour; this caps attempts from many addresses.
                Limit::perHour(30)->by('shared-unlock-link:'.SharedLinks::hash(is_string($token) ? $token : '')),
            ];
        });

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

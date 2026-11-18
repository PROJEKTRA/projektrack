<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Auth\AuthenticateUser;
use App\Auth\AuthenticationSubscriber;
use App\Auth\ConfirmIdentity;
use App\Auth\EnsureAccountIsNotLocked;
use App\Auth\StartTwoFactorChallenge;
use App\Auth\TwoFactor\DisableTwoFactorAuthentication;
use App\Auth\TwoFactor\EnableTwoFactorAuthentication;
use App\Auth\TwoFactor\GenerateNewRecoveryCodes;
use App\Auth\TwoFactor\RecoveryCodeController;
use App\Auth\TwoFactor\TwoFactorLoginRequest;
use App\Auth\UniformPasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisable;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyEnable;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes as FortifyGenerate;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController as FortifyRecoveryCodeController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Two-factor per S-0904: hashed single-use recovery codes shown once, and
        // no switching off while a role requires it.
        $this->app->bind(FortifyEnable::class, EnableTwoFactorAuthentication::class);
        $this->app->bind(FortifyDisable::class, DisableTwoFactorAuthentication::class);
        $this->app->bind(FortifyGenerate::class, GenerateNewRecoveryCodes::class);
        $this->app->bind(FortifyTwoFactorLoginRequest::class, TwoFactorLoginRequest::class);
        $this->app->bind(FortifyRecoveryCodeController::class, RecoveryCodeController::class);

        // S-0903: the reset link form answers the same for every address.
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, UniformPasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, UniformPasswordResetLinkResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // S-0903: a locked account name is refused first; credentials are
        // checked with equal cost for unknown addresses.
        Fortify::authenticateUsing(fn (Request $request): ?User => app(AuthenticateUser::class)($request));
        Fortify::authenticateThrough(fn (): array => [
            EnsureAccountIsNotLocked::class,
            StartTwoFactorChallenge::class,
            RedirectIfTwoFactorAuthenticatable::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]);

        // Re-authentication accepts the password or a current authenticator code.
        Fortify::confirmPasswordsUsing(fn (mixed $user, mixed $password): bool => app(ConfirmIdentity::class)($user, $password));

        Event::subscribe(AuthenticationSubscriber::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn (Request $request) => Inertia::render('auth/ConfirmPassword', [
            'acceptsCode' => $request->user() instanceof User && ConfirmIdentity::acceptsCode($request->user()),
        ]));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Per address: App\Auth\LoginThrottle (progressive lock). Per IP: 20 per
        // minute, against spraying many addresses (security baseline section 2).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by('ip:'.$request->ip()));

    }
}

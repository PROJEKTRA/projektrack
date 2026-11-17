<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\Authorization\TwoFactorRequirement;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforced two-factor (S-0904): a signed-in user whose roles require it and
 * who has not enrolled reaches only the enrollment steps, until enrolled. No
 * grace period.
 */
class EnsureTwoFactorEnrolled
{
    /** Routes needed to enroll (or to leave). Everything else is blocked. */
    public const array ENROLLMENT_ROUTES = [
        'security.edit',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'logout',
    ];

    public function __construct(private readonly TwoFactorRequirement $requirement) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && ! $user->hasEnabledTwoFactorAuthentication()
            && ! $request->routeIs(...self::ENROLLMENT_ROUTES)
            && $this->requirement->isRequired($user)) {
            abort_if($request->expectsJson(), 403, __('two_factor.required'));

            return redirect()->route('security.edit');
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

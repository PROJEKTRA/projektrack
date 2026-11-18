<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer to "send me a reset link" (S-0903): the same for a known
 * address, an unknown one and a known one asked too often, so the form does
 * not reveal which addresses have an account.
 */
final class UniformPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(private readonly string $status = Password::RESET_LINK_SENT) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        // A sent link cost a token hash; spend the same when none was sent.
        if ($this->status !== Password::RESET_LINK_SENT) {
            Hash::make(Str::random(40));
        }

        $message = __('core:passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}

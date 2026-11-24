<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use Inertia\Inertia;
use Laravel\Fortify\Http\Responses\LogoutResponse;

/** Signing out also makes the pages in the browser history unreadable. */
final class SignedOutResponse extends LogoutResponse
{
    public function toResponse($request)
    {
        Inertia::clearHistory();

        return parent::toResponse($request);
    }
}

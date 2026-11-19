<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
 * REST API authentication (S-0913): personal access tokens only. The API
 * accepts no session cookies, so a page in a browser cannot call it on the
 * user's behalf; the browser UI uses its own web routes.
 */
return [

    // No first-party SPA authentication over the API, so no CSRF cookie route either.
    'stateful' => [],
    'routes' => false,

    // No guard is tried before the token: session users are not API users.
    'guard' => [],

    // Each token carries its own expiry (default 90 days, App\Api\ApiTokens).
    'expiration' => null,

    // Prefix for secret scanning (security baseline section 2).
    'token_prefix' => 'ptk_',

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => PreventRequestForgery::class,
    ],

];

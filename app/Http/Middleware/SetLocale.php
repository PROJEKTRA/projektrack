<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\I18n\Strings;
use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the request language from the first link of the chain (S-0916): the
 * signed-in user's language, else the tenant language, else bs. Nothing from
 * the browser is read, so a guest page reveals nothing about the visitor.
 */
final class SetLocale
{
    public function __construct(private readonly Strings $strings, private readonly TenantContext $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $preferred = $user instanceof User ? $user->locale : null;
        $preferred ??= $this->tenants->has() ? $this->tenants->current()->default_locale : null;

        App::setLocale($this->strings->chain($preferred)[0]);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

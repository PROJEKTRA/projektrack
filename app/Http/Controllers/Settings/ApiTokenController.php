<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Settings;

use App\Api\ApiScopes;
use App\Api\ApiTokens;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings > API tokens (S-0913): create a scoped, expiring token (shown
 * once) and revoke tokens. Needs core.api_tokens.manage and a recent
 * confirmation (route middleware).
 */
class ApiTokenController extends Controller
{
    public function index(Request $request, ApiTokens $tokens, ApiScopes $scopes): Response
    {
        return Inertia::render('settings/ApiTokens', [
            'tokens' => array_map(fn (ApiToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->abilities,
                'lastUsedAt' => $token->last_used_at?->toIso8601String(),
                'expiresAt' => $token->expires_at->toIso8601String(),
                'expired' => $token->expires_at->isPast(),
            ], $tokens->of($this->currentUser($request))),
            'scopes' => array_map(
                fn (string $scope, string $label): array => ['value' => $scope, 'label' => __($label)],
                array_keys($scopes->all()),
                array_values($scopes->all()),
            ),
            'defaultDays' => ApiTokens::DEFAULT_DAYS,
            'maxDays' => ApiTokens::MAX_DAYS,
        ]);
    }

    public function store(Request $request, ApiTokens $tokens, ApiScopes $scopes): RedirectResponse
    {
        /** @var array{name: string, scopes: list<string>, days: int|string} $valid */
        $valid = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(array_keys($scopes->all()))],
            'days' => ['required', 'integer', 'min:1', 'max:'.ApiTokens::MAX_DAYS],
        ]);

        $created = $tokens->create($this->currentUser($request), trim($valid['name']), $valid['scopes'], (int) $valid['days']);

        // Shown once, on the next page; only its hash is stored.
        Inertia::flash('newToken', $created['secret']);

        return back();
    }

    public function destroy(Request $request, ApiTokens $tokens, string $token): RedirectResponse
    {
        abort_unless($tokens->revoke($this->currentUser($request), $token), 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:api_tokens.revoked')]);

        return back();
    }
}

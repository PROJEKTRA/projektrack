<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Api\V1;

use App\Api\ApiOperation;
use App\Authorization\Permissions;
use App\Http\Controllers\Controller;
use App\I18n\Formats;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The token's user (S-0913).
 */
class MeController extends Controller
{
    #[ApiOperation(
        summary: 'The user the token belongs to, with their permissions and the token scopes.',
        scope: 'core:read',
        response: [
            'type' => 'object',
            'required' => ['id', 'name', 'email', 'locale', 'format', 'permissions', 'scopes'],
            'properties' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'locale' => ['type' => ['string', 'null']],
                'format' => ['type' => 'object', 'properties' => ['locale' => ['type' => 'string'], 'timeZone' => ['type' => 'string']]],
                'permissions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'scopes' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ],
    )]
    public function show(Request $request, Permissions $permissions, Formats $formats): JsonResponse
    {
        $user = $this->currentUser($request);
        $token = $user->currentAccessToken();
        $format = $formats->for($user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'format' => ['locale' => $format->locale, 'timeZone' => $format->timezone],
            'permissions' => $permissions->of($user),
            'scopes' => $token instanceof ApiToken ? $token->abilities : [],
        ]);
    }
}

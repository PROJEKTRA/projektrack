<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Api\V1;

use App\Api\ApiOperation;
use App\Http\Controllers\Controller;
use App\I18n\Strings;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The token user's notifications (S-0913, same data as the UI of S-0912).
 */
class NotificationController extends Controller
{
    #[ApiOperation(
        summary: 'The newest 50 notifications of the token user, written in their language.',
        scope: 'core:read',
        response: [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'required' => ['id', 'type', 'message', 'read', 'created_at'],
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                        'url' => ['type' => ['string', 'null']],
                        'read' => ['type' => 'boolean'],
                        'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    ],
                ]],
            ],
        ],
    )]
    public function index(Request $request, Strings $strings): JsonResponse
    {
        $user = $this->currentUser($request);
        $data = AppNotification::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AppNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'message' => $strings->get($notification->data['message'], $notification->data['args'] ?? [], $user->preferredLocale()),
                'url' => $notification->data['url'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->all();

        return response()->json(['data' => $data]);
    }

    #[ApiOperation(
        summary: 'Mark every notification of the token user as read.',
        scope: 'core:write',
        response: ['type' => 'object', 'required' => ['marked'], 'properties' => ['marked' => ['type' => 'integer']]],
    )]
    public function readAll(Request $request): JsonResponse
    {
        $marked = AppNotification::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['marked' => $marked]);
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers;

use App\I18n\Strings;
use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's notifications (S-0912): list, open one, mark all read.
 * Messages are written in the reader's language at display time.
 */
class NotificationController extends Controller
{
    public const int PER_PAGE = 50;

    public function index(Request $request, Strings $strings): Response
    {
        $user = $this->currentUser($request);
        $items = AppNotification::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->latest('id')
            ->limit(self::PER_PAGE)
            ->get()
            ->map(fn (AppNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'message' => $strings->get($notification->data['message'], $notification->data['args'] ?? []),
                'url' => $notification->data['url'] ?? null,
                'read' => $notification->read_at !== null,
                'createdAt' => $notification->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('notifications/Index', ['notifications' => $items]);
    }

    /** Mark one as read and go where it leads. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = AppNotification::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->findOrFail($notification);

        $item->read_at ??= now();
        $item->save();

        $url = $item->data['url'] ?? null;

        // Only paths inside the application, never another site.
        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : to_route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        AppNotification::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}

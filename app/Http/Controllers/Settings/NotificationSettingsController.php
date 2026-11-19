<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Notifications\NotificationPreferences;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings > Notifications (S-0912): e-mail on or off per notification type.
 */
class NotificationSettingsController extends Controller
{
    public function edit(Request $request, NotificationPreferences $preferences, Settings $settings): Response
    {
        $types = array_map(fn (array $type): array => [...$type, 'label' => __($type['label'])], $preferences->of($this->currentUser($request)));

        return Inertia::render('settings/Notifications', [
            'types' => $types,
            // The organization may have turned e-mail off for everything but security notices.
            'emailEnabled' => $settings->get('core.email_notifications') === true,
        ]);
    }

    public function update(Request $request, NotificationPreferences $preferences): RedirectResponse
    {
        /** @var array{mail: array<string, mixed>} $valid */
        $valid = $request->validate([
            'mail' => ['present', 'array'],
            'mail.*' => ['boolean'],
        ]);

        $mail = [];
        foreach ($valid['mail'] as $type => $on) {
            $mail[(string) $type] = filter_var($on, FILTER_VALIDATE_BOOLEAN);
        }
        $preferences->save($this->currentUser($request), $mail);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:flash.settings_saved')]);

        return back();
    }
}

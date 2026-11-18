<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Settings;

use App\Audit\AuditLog;
use App\Auth\Sessions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Sign out every other session of the signed-in user (S-0903). Needs a recent
 * confirmation (route middleware).
 */
class SessionController extends Controller
{
    public function destroy(Request $request, Sessions $sessions, AuditLog $audit): RedirectResponse
    {
        $user = $this->currentUser($request);
        $ended = $sessions->endOthers($user, $request->session());

        $audit->record('auth.sessions_ended', $user, ['count' => $ended]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:flash.other_sessions_ended')]);

        return back();
    }
}

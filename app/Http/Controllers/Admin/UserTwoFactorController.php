<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Admin;

use App\Auth\TwoFactor\ResetTwoFactor;
use App\Authorization\Permissions;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserTwoFactorController extends Controller
{
    /**
     * Reset a user's two-factor setup. The route requires core.users.manage and
     * a password confirmation within the last 15 minutes.
     */
    public function destroy(Request $request, User $user, ResetTwoFactor $reset, Permissions $permissions): RedirectResponse
    {
        // Nobody weakens the sign-in of someone who may do more.
        abort_if(array_diff($permissions->of($user), $permissions->of($this->currentUser($request))) !== [], 403);

        $reset($user, $this->currentUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:two_factor.reset_done')]);

        return back();
    }
}

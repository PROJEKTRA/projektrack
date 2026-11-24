<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Settings;

use App\Audit\AuditLog;
use App\Authorization\RoleManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\I18n\Formats;
use App\I18n\Strings;
use App\I18n\TenantLanguages;
use App\Notifications\EmailWasChanged;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly RoleManager $roles,
        private readonly TenantLanguages $languages,
        private readonly Strings $strings,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, Formats $formats): Response
    {
        $user = $this->currentUser($request);

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'locale' => $user->locale,
            // Each language under its own name, so it is found in any interface language.
            // A language the tenant deactivated is no longer offered (S-0921).
            'locales' => $this->languages->options(),
            // Regional format and time zone, separate from the language (S-0919).
            'formatLocale' => $user->format_locale,
            'formats' => $formats->options(App::getLocale()),
            'timezone' => $user->timezone,
            'timezones' => $formats->timezones(),
            'tenantTimezone' => app(TenantContext::class)->current()->timezone,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $former = $user->email;
        $user->fill($request->profile());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Field names only, never the old or new values.
        $changed = array_keys($user->getDirty());

        DB::transaction(function () use ($user, $changed): void {
            $user->save();

            if ($changed !== []) {
                $this->audit->record('users.profile_updated', $user, ['fields' => $changed]);
            }
        });

        // The old address hears of it, so a takeover is noticed.
        if ($user->email !== $former) {
            Notification::route('mail', $former)->notify(new EmailWasChanged($user));
        }

        // A new language applies to this confirmation already.
        App::setLocale($this->strings->chain($user->locale ?? app(TenantContext::class)->current()->default_locale)[0]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:flash.profile_updated')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        // Booked time and issued documents keep their people.
        try {
            DB::transaction(function () use ($user): void {
                if ($this->roles->isOnlyAdministrator($user)) {
                    throw ValidationException::withMessages(['password' => __('core:users.last_admin')]);
                }
                $this->audit->record('users.deleted', $user, [], $user);
                $user->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['password' => __('core:profile.in_use')]);
        }

        // Not logout(): it would write the deleted user again (remember token).
        Auth::guard('web')->logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

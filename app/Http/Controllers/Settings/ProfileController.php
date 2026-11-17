<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Settings;

use App\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\I18n\Locales;
use App\I18n\Strings;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Locale;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly Locales $locales,
        private readonly Strings $strings,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'locale' => $this->currentUser($request)->locale,
            // Each language under its own name, so it is found in any interface language.
            'locales' => array_map(fn (string $locale): array => [
                'value' => $locale,
                'label' => mb_convert_case(Locale::getDisplayName($locale, $locale) ?: $locale, MB_CASE_TITLE),
            ], $this->locales->available()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $user->fill($request->validated());

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

        $this->audit->record('users.deleted', $user, [], $user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

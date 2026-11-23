<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\Authorization\Permissions;
use App\Branding\Brand;
use App\Doctor\Doctor;
use App\Doctor\DoctorAccess;
use App\I18n\Formats;
use App\I18n\Strings;
use App\Licensing\LicenseAccess;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            // Brand of the tenant (S-0911); never the product name from configuration.
            'name' => app(Brand::class)->nameForRequest(),
            'brand' => app(Brand::class)->toArray(),
            'auth' => [
                'user' => $user,
                // For showing or hiding UI only; the server checks every action again.
                'permissions' => $user instanceof User ? app(Permissions::class)->of($user) : [],
            ],
            // Which string bundle the page needs (S-0916).
            'strings' => $this->strings(),
            // Regional format and time zone for dates and amounts (S-0919).
            'formats' => app(Formats::class)->current()->toArray(),
            // Menu entry of the license, for operator administrators (S-0976).
            'licenseAccess' => fn (): bool => $user instanceof User && app(LicenseAccess::class)->allows($user),
            // Critical findings of the system check for operator administrators
            // (S-0988); null hides the menu entry for everyone else.
            'doctorAlerts' => fn (): ?int => $user instanceof User && app(DoctorAccess::class)->allows($user)
                ? app(Doctor::class)->criticalProblems()
                : null,
            // Unread notifications for the bell (S-0912).
            'unreadNotifications' => fn (): int => $user instanceof User
                ? AppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count()
                : 0,
            // Integrity warning for those who may read the audit log (S-0907).
            'auditWarning' => fn (): ?string => $this->auditWarning($user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{locale: string, dir: string, version: string, url: string}
     */
    private function strings(): array
    {
        $strings = app(Strings::class);
        $chain = $strings->chain();
        $version = $strings->bundleVersion($chain);

        return [
            'locale' => $chain[0],
            'dir' => $strings->direction($chain[0]),
            'version' => $version,
            'url' => route('strings.bundle', ['chain' => implode('.', $chain), 'version' => $version], false),
        ];
    }

    private function auditWarning(mixed $user): ?string
    {
        if (! $user instanceof User || ! app(Permissions::class)->allows($user, 'core.audit.view')) {
            return null;
        }

        $broken = DB::table('audit_chain_heads')->where('tenant_id', $user->tenant_id)->value('broken_at_seq');

        if (! is_numeric($broken)) {
            return null;
        }

        return app(Strings::class)->get('core:audit.integrity_warning', ['seq' => (int) $broken]);
    }
}

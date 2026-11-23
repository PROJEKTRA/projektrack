<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Admin;

use App\Audit\AuditLog;
use App\Authorization\UserType;
use App\Http\Controllers\Controller;
use App\Licensing\InstanceId;
use App\Licensing\InstanceLicense;
use App\Licensing\InvalidLicense;
use App\Licensing\LicenseAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > License (S-0976): what the installation is licensed for, and a
 * license installed offline. Operator administrators only (LicenseAccess);
 * anyone else gets the plain 404.
 */
class LicenseController extends Controller
{
    public function show(Request $request, LicenseAccess $access, InstanceLicense $licenses, InstanceId $instance): Response
    {
        abort_unless($access->allows($this->currentUser($request)), 404);

        $license = $licenses->current();
        try {
            $instanceId = $instance->get();
            $clockProblem = $licenses->clockProblem();
        } catch (InvalidLicense) {
            $instanceId = null;
            $clockProblem = false;
        }

        return Inertia::render('admin/License', [
            'instanceId' => $instanceId,
            'clockProblem' => $clockProblem,
            'status' => $licenses->status()?->value,
            'used' => ['full' => $this->count(UserType::Full), 'light' => $this->count(UserType::Light)],
            'license' => $license === null ? null : [
                'edition' => $license->edition->value,
                'customer' => $license->customer,
                'id' => $license->id,
                'serial' => $license->serial,
                'full' => $license->fullSeats,
                'light' => $license->lightSeats,
                'expiresAt' => $license->expiresAt->toIso8601ZuluString(),
                'readOnlyFrom' => $license->expiresAt->addDays($license->expiryGraceDays)->toIso8601ZuluString(),
                'statusUntil' => $license->statusUntil?->toIso8601ZuluString(),
                'addons' => $license->addons,
                'features' => $license->features,
                'domains' => $license->domains,
                'domainCovered' => $license->coversDomain($request->getHost()),
            ],
        ]);
    }

    public function store(Request $request, LicenseAccess $access, InstanceLicense $licenses, AuditLog $audit): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless($access->allows($user), 404);
        $request->validate(['license' => ['required', 'string', 'max:16384']]);

        try {
            $license = $licenses->install($request->string('license')->toString());
        } catch (InvalidLicense $e) {
            $audit->record('license.refused', null, ['reason' => $e->reason], $user);

            throw ValidationException::withMessages(['license' => __('core:license.refused.'.$e->reason)]);
        }

        Log::notice('License installed', ['license_id' => $license->id, 'serial' => $license->serial]);
        $audit->record('license.installed', ['instance_licenses', $license->id], ['serial' => $license->serial, 'edition' => $license->edition->value], $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:license.installed')]);

        return to_route('admin.license.show');
    }

    /** Users of every tenant, as ADR-0003 section 2 counts them. */
    private function count(UserType $type): int
    {
        return DB::table('users')->where('user_type', $type->value)->count();
    }
}

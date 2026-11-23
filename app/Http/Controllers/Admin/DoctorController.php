<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Admin;

use App\Audit\AuditLog;
use App\Doctor\Doctor;
use App\Doctor\DoctorAccess;
use App\Doctor\Finding;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > System check (S-0988): the last report of `projektrack doctor`
 * with plain-language fixes, and a button to check again. Operator
 * administrators only (DoctorAccess); anyone else gets the plain 404.
 */
class DoctorController extends Controller
{
    public function index(Request $request, Doctor $doctor, DoctorAccess $access): Response
    {
        abort_unless($access->allows($this->currentUser($request)), 404);
        $last = $doctor->last();

        return Inertia::render('admin/Doctor', [
            'checkedAt' => $last['at'] ?? null,
            'findings' => $last === null ? [] : array_map(fn (Finding $finding): array => [
                'check' => $finding->check,
                'severity' => $finding->severity,
                'ok' => $finding->ok,
                'detail' => $finding->detail,
                'title' => __($finding->title()),
                'fix' => __($finding->fix()),
            ], $last['findings']),
        ]);
    }

    /**
     * The scheduler runs the check within a minute: no queue worker needed on
     * plain hosting, and repeated requests make one run.
     */
    public function store(Request $request, DoctorAccess $access, Repository $cache, AuditLog $audit): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless($access->allows($user), 404);

        $cache->put(Doctor::REQUESTED_KEY, true, now()->addHour());
        $audit->record('doctor.requested', null, [], $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:doctor.page.requested')]);

        return back();
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Timesheets\Domain\Timers;

class TimerController extends Controller
{
    public function start(Request $request, Timers $timers): RedirectResponse
    {
        $request->validate(['work_package_id' => ['required', 'ulid']]);
        $timers->start($this->currentUser($request), $request->string('work_package_id')->lower()->toString());

        return to_route('timesheets.index');
    }

    public function stop(Request $request, Timers $timers): RedirectResponse
    {
        $request->validate(['description' => ['nullable', 'string', 'max:2000']]);
        $note = $request->input('description');
        $minutes = $timers->stop($this->currentUser($request), is_string($note) ? $note : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('timesheets:timer.booked', ['minutes' => $minutes])]);

        return to_route('timesheets.index');
    }

    public function discard(Request $request, Timers $timers): RedirectResponse
    {
        $timers->discard($this->currentUser($request));

        return to_route('timesheets.index');
    }
}

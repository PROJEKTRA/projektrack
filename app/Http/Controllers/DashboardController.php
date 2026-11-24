<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers;

use App\Dashboard\Dashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard): Response
    {
        $user = $this->currentUser($request);
        // Setting up the company is for those who may; nobody else waits for the checks.
        $steps = $user->can('core.settings.manage') ? $dashboard->steps() : [];

        return Inertia::render('Dashboard', ['cards' => $dashboard->for($user), 'steps' => array_column($steps, 'done') === array_fill(0, count($steps), true) ? [] : $steps]);
    }
}

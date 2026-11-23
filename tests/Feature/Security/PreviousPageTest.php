<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;

/*
 * Without a referrer, validation errors return to the page of the visit.
 */

it('returns to the page reached inside the application', function () {
    $this->actingAs(User::factory()->create());
    $this->get('/dashboard')->assertOk();
    $version = (string) app(HandleInertiaRequests::class)->version(request());
    $this->get('/settings/profile', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => $version])->assertOk();
    $this->patch('/settings/profile', ['name' => ''], ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])->assertRedirect('/settings/profile');
});

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Licensing\EditionGate;
use App\Models\Role;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\Edition;
use App\Modules\EditionNotLicensed;
use App\Modules\TenantModules;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeModules;

/*
 * S-0977: commercial modules need a license; Community never does. S-0936: first steps.
 */

it('never limits Community and refuses commercial modules without a license', function () {
    $gate = app(EditionGate::class);
    expect($gate->allows(Edition::Community))->toBeTrue()->and($gate->allows(Edition::Business))->toBeFalse();
    FakeModules::install(['reminders' => ['edition' => 'business']]);
    app()->forgetScopedInstances();
    expect(fn () => app(TenantModules::class)->enable('reminders'))->toThrow(EditionNotLicensed::class)
        ->and(app(TenantModules::class)->isEnabled('reminders'))->toBeFalse();
    $this->artisan('projektrack:modules:enable', ['tenant' => $this->tenant->slug, 'module' => 'reminders'])->assertFailed();
});

it('lets a commercial module be read when its license is gone, not changed', function () {
    FakeModules::install(['reminders' => ['edition' => 'business']]);
    app()->forgetScopedInstances();
    TenantModule::query()->create(['module_key' => 'reminders', 'enabled' => true, 'enabled_at' => now()]);
    Route::middleware(['web', 'auth', 'module:reminders', 'access:self'])->group(function () {
        Route::get('gate-probe', fn () => 'ok');
        Route::post('gate-probe', fn () => 'ok');
    });
    $this->actingAs(User::factory()->create())->get('/gate-probe')->assertOk();
    $this->post('/gate-probe')->assertForbidden();
});

it('shows the first steps to administrators until all are done', function () {
    $admin = User::factory()->withTwoFactor()->create();
    app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());
    app(TenantModules::class)->enable('crm');
    $steps = $this->actingAs($admin)->get('/dashboard')->inertiaProps('steps');
    expect(array_column($steps, 'done', 'label'))->toBe(['core:dashboard.step.team' => false, 'crm:dashboard.step' => false]);
    User::factory()->create();
    expect($this->get('/dashboard')->inertiaProps('steps.0.done'))->toBeTrue();
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertInertia(fn (AssertableInertia $p) => $p->has('steps', 0));
});

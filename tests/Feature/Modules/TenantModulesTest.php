<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Modules\ModuleStillRequired;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Psr\Log\NullLogger;

/*
 * S-0901 on top of S-0902: modules are switched per tenant.
 */

beforeEach(function () {
    $root = sys_get_temp_dir().'/ptk-tm-'.bin2hex(random_bytes(6));
    $make = function (string $key, array $requires) use ($root): void {
        mkdir("{$root}/{$key}", recursive: true);
        file_put_contents("{$root}/{$key}/module.json", (string) json_encode([
            'key' => $key, 'name_string' => "{$key}:module_name", 'version' => '0.9.0', 'core' => '^0.9',
            'edition' => 'community', 'requires' => $requires, 'permissions' => ["{$key}.view"],
        ]));
    };
    $make('crm', ['core']);
    $make('projects', ['core', 'crm']);
    $make('timesheets', ['core', 'projects']);

    app()->instance(ModuleRegistry::class, new ModuleRegistry([$root], new NullLogger));
    app()->forgetScopedInstances();

    Route::middleware(['web', 'module:timesheets'])->get('/_test/timesheets', fn () => 'timesheets');
});

it('enables required modules together with the chosen one and tells the caller which', function () {
    $modules = app(TenantModules::class);

    expect($modules->enablePlan('timesheets'))->toBe(['crm', 'projects', 'timesheets']);

    $enabled = $modules->enable('timesheets', User::factory()->create());

    expect($enabled)->toBe(['crm', 'projects', 'timesheets'])
        ->and($modules->enabled())->toBe(['crm', 'projects', 'timesheets']);
});

it('refuses to disable a module that enabled modules still need', function () {
    $modules = app(TenantModules::class);
    $modules->enable('timesheets');

    expect(fn () => $modules->disable('crm'))->toThrow(ModuleStillRequired::class);

    $modules->disable('timesheets');
    expect($modules->enabled())->toBe(['crm', 'projects']);
});

it('keeps module switches separate per tenant', function () {
    app(TenantModules::class)->enable('timesheets');

    $other = Tenant::factory()->create();
    $enabledThere = app(TenantContext::class)->run($other, function () {
        app()->forgetScopedInstances();

        return app(TenantModules::class)->enabled();
    });

    expect($enabledThere)->toBe([]);
});

it('answers routes of a disabled module with a plain 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/_test/timesheets')->assertNotFound();

    app(TenantModules::class)->enable('timesheets');
    app()->forgetScopedInstances();

    $this->actingAs($user)->get('/_test/timesheets')->assertOk()->assertSee('timesheets');
});

it('re-enabling a module after disabling it changes only the switch', function () {
    $modules = app(TenantModules::class);
    $modules->enable('timesheets');
    $modules->disable('timesheets');
    $modules->enable('timesheets');

    expect($modules->enabled())->toBe(['crm', 'projects', 'timesheets'])
        ->and(TenantModule::query()->count())->toBe(3);
});

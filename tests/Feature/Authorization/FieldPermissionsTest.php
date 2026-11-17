<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\FieldAccess;
use App\Authorization\FieldGate;
use App\Authorization\InvalidRoleChange;
use App\Authorization\RoleManager;
use App\Models\Role;
use App\Models\User;
use App\Modules\TenantModules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Tests\Support\FakeModules;

/*
 * S-0906, "Field-level permission for rates": without timesheets.view_rates no
 * rate, cost or amount is contained in the page props or the API response.
 */

const TIME_ENTRY = [
    'id' => 'te-1',
    'description' => 'Server maintenance',
    'minutes' => 90,
    'sales_amount' => 13_500,
    'cost_amount' => 8_100,
];

beforeEach(function () {
    FakeModules::install([
        'timesheets' => [
            'permissions' => ['timesheets.view', 'timesheets.book', 'timesheets.view_rates'],
            'field_permissions' => [
                ['entity' => 'time_entries', 'field' => 'sales_amount', 'default' => 'hidden', 'risk' => 'financial', 'permission' => 'timesheets.view_rates'],
                ['entity' => 'time_entries', 'field' => 'cost_amount', 'default' => 'hidden', 'risk' => 'financial'],
                ['entity' => 'time_entries', 'field' => 'description', 'default' => 'write', 'risk' => 'none'],
            ],
            'role_defaults' => ['employee' => ['timesheets.view', 'timesheets.book'], 'controller' => ['timesheets.view', 'timesheets.view_rates']],
        ],
    ]);
    app(TenantModules::class)->enable('timesheets');
    app()->forgetScopedInstances();

    $entry = fn (Request $request): array => app(FieldGate::class)->filter($request->user(), 'time_entries', TIME_ENTRY);

    Route::middleware(['web', 'auth', 'permission:timesheets.view'])->group(function () use ($entry) {
        Route::get('/_test/api/time-entries/te-1', fn (Request $request): JsonResponse => response()->json(['data' => $entry($request)]));
        Route::get('/_test/time-entries/te-1', fn (Request $request): Response => Inertia::render('Dashboard', ['entry' => $entry($request)]));
    });
});

function withRole(string $key): User
{
    // Enrolled in two-factor, so roles that require it can reach the routes.
    $user = User::factory()->withTwoFactor()->create();
    app(RoleManager::class)->assign($user, Role::query()->where('key', $key)->firstOrFail());

    return $user;
}

it('leaves rates and costs out of the API response for an employee', function () {
    $response = $this->actingAs(withRole('employee'))->getJson('/_test/api/time-entries/te-1')->assertOk();

    $response->assertJsonPath('data.minutes', 90)
        ->assertJsonMissingPath('data.sales_amount')
        ->assertJsonMissingPath('data.cost_amount');

    expect($response->getContent())->not->toContain('13500')->not->toContain('8100');
});

it('leaves rates and costs out of the page props for an employee', function () {
    $response = $this->actingAs(withRole('employee'))->get('/_test/time-entries/te-1')->assertOk();

    $response->assertInertia(fn ($page) => $page->where('entry.minutes', 90)
        ->missing('entry.sales_amount')
        ->missing('entry.cost_amount'));

    expect($response->getContent())->not->toContain('13500')->not->toContain('8100');
});

it('shows the sales amount to holders of timesheets.view_rates, but not the cost', function () {
    $this->actingAs(withRole('controller'))
        ->getJson('/_test/api/time-entries/te-1')
        ->assertJsonPath('data.sales_amount', 13_500)
        ->assertJsonMissingPath('data.cost_amount');
});

it('lets a role override open a field, and takes the highest access across roles', function () {
    $user = withRole('employee');
    $gate = app(FieldGate::class);

    expect($gate->access($user, 'time_entries', 'cost_amount'))->toBe(FieldAccess::Hidden);

    app(RoleManager::class)->setFieldAccess(Role::query()->where('key', 'controller')->firstOrFail(), 'time_entries', 'cost_amount', FieldAccess::Read);
    app(RoleManager::class)->assign($user, Role::query()->where('key', 'controller')->firstOrFail());

    expect($gate->access($user, 'time_entries', 'cost_amount'))->toBe(FieldAccess::Read);
});

it('lets a role override hide a field that is visible by default', function () {
    $user = withRole('employee');
    app(RoleManager::class)->setFieldAccess(Role::query()->where('key', 'employee')->firstOrFail(), 'time_entries', 'description', FieldAccess::Hidden);

    expect(app(FieldGate::class)->filter($user, 'time_entries', TIME_ENTRY))->not->toHaveKey('description');

    app(RoleManager::class)->setFieldAccess(Role::query()->where('key', 'employee')->firstOrFail(), 'time_entries', 'description', null);

    expect(app(FieldGate::class)->filter($user, 'time_entries', TIME_ENTRY))->toHaveKey('description');
});

it('never shows financial fields to portal users', function () {
    $portal = User::factory()->portal()->create();

    expect(app(FieldGate::class)->hiddenFields($portal, 'time_entries'))->toBe(['sales_amount', 'cost_amount']);
});

it('hides the fields of a disabled module', function () {
    $controller = withRole('controller');
    app(TenantModules::class)->disable('timesheets');

    expect(app(FieldGate::class)->canRead($controller, 'time_entries', 'description'))->toBeFalse();
});

it('refuses overrides for fields nobody declared', function () {
    expect(fn () => app(RoleManager::class)->setFieldAccess(Role::query()->where('key', 'employee')->firstOrFail(), 'time_entries', 'unknown', FieldAccess::Read))
        ->toThrow(InvalidRoleChange::class);
});

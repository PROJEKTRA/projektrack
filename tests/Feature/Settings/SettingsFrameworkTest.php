<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\I18n\Formats;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Settings\Settings;
use App\Settings\SettingsSchema;
use App\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeModules;

/*
 * S-0912: settings from module schemas, edited in Admin > Settings.
 */

function settingsAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());

    return $admin;
}

/** A module "reminders" with its own settings.schema.json, enabled for the tenant. */
function remindersModule(): void
{
    FakeModules::install(
        ['reminders' => ['settings_schema' => 'settings.schema.json']],
        ['reminders' => ['settings.schema.json' => (string) json_encode([
            'type' => 'object',
            'properties' => [
                'lead_days' => ['type' => 'integer', 'default' => 3, 'minimum' => 1, 'maximum' => 30, 'x-label' => 'core:common.save'],
                'channel' => ['type' => 'string', 'default' => 'mail', 'enum' => ['mail', 'app'], 'x-enum-labels' => ['core:common.save', 'core:common.cancel'], 'x-label' => 'core:common.cancel'],
            ],
        ])]],
    );
    app(TenantModules::class)->enable('reminders');
    app()->forgetScopedInstances();
}

it('renders a validated form for every component with defaults and translated labels', function () {
    remindersModule();

    $this->actingAs(settingsAdmin())->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Settings')
            ->where('groups.0.component', 'core')
            ->where('groups.0.fields', fn ($fields) => collect($fields)->firstWhere('name', 'email_notifications')['value'] === true
                && collect($fields)->firstWhere('name', 'timezone')['label'] === __('core:admin_settings.timezone.label', [], 'en'))
            ->where('groups.1.component', 'reminders')
            ->where('groups.1.fields.0.value', 3)
            ->where('groups.1.fields.1.options.1', ['value' => 'app', 'label' => __('core:common.cancel', [], 'en')]));
});

it('stores only values that differ from the default, validated against the schema', function () {
    remindersModule();
    $admin = settingsAdmin();

    $this->actingAs($admin)->put('/admin/settings/reminders', ['lead_days' => 31, 'channel' => 'fax'])
        ->assertSessionHasErrors(['lead_days', 'channel']);
    expect(Setting::query()->count())->toBe(0);

    $this->actingAs($admin)->put('/admin/settings/reminders', ['lead_days' => '7', 'channel' => 'mail'])
        ->assertSessionHasNoErrors();

    app()->forgetScopedInstances();
    expect(app(Settings::class)->get('reminders.lead_days'))->toBe(7)
        ->and(Setting::query()->pluck('key')->all())->toBe(['reminders.lead_days'])
        ->and(AuditEntry::query()->where('action', 'settings.changed')->latest('seq')->first()?->contextData())
        ->toBe(['component' => 'reminders', 'keys' => ['lead_days']]);

    // Back to the default: the row goes, so a changed default applies later.
    $this->actingAs($admin)->put('/admin/settings/reminders', ['lead_days' => 3, 'channel' => 'mail']);
    expect(Setting::query()->count())->toBe(0);
});

it('keeps the organization language and time zone on the tenant', function () {
    $this->actingAs(settingsAdmin())->put('/admin/settings/core', [
        'default_locale' => 'en',
        'format_locale' => 'de-DE',
        'timezone' => 'Europe/Sarajevo',
        'email_notifications' => '1',
    ])->assertSessionHasNoErrors();

    $tenant = $this->tenant->refresh();
    app()->forgetScopedInstances();

    expect($tenant->default_locale)->toBe('en')
        ->and($tenant->timezone)->toBe('Europe/Sarajevo')
        // Users without an own format now get the organization's.
        ->and(app(Formats::class)->for(User::factory()->create())->locale)->toBe('de-DE');
});

it('keeps settings inside their tenant', function () {
    $this->actingAs(settingsAdmin())->put('/admin/settings/core', [
        'default_locale' => 'bs',
        'format_locale' => 'en-US',
        'timezone' => 'UTC',
        'email_notifications' => '0',
    ]);

    app(TenantContext::class)->run(Tenant::factory()->create(), function () {
        app()->forgetScopedInstances();
        expect(app(Settings::class)->get('core.email_notifications'))->toBeTrue()
            ->and(app(Settings::class)->get('core.format_locale'))->toBeNull();
    });
});

it('is only for users allowed to manage settings', function () {
    $employee = User::factory()->create();
    app(RoleManager::class)->assign($employee, Role::query()->where('key', 'employee')->firstOrFail());

    $this->actingAs($employee)->get('/admin/settings')->assertForbidden();
    $this->actingAs($employee)->put('/admin/settings/core', [])->assertForbidden();
});

it('answers unknown components with 404', function () {
    $this->actingAs(settingsAdmin())->put('/admin/settings/nothing_here', [])->assertNotFound();
});

it('refuses schemas it cannot render or validate correctly', function (array $property) {
    expect(fn () => SettingsSchema::fromArray('reminders', ['type' => 'object', 'properties' => ['x' => $property]]))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'no label' => [['type' => 'string']],
    'label not an identifier' => [['type' => 'string', 'x-label' => 'Lead days']],
    'unsupported type' => [['type' => 'array', 'x-label' => 'core:common.save']],
    'unsupported keyword' => [['type' => 'string', 'format' => 'uri', 'x-label' => 'core:common.save']],
    'module writing a tenant column' => [['type' => 'string', 'x-tenant-attribute' => 'timezone', 'x-label' => 'core:common.save']],
    'unknown option list' => [['type' => 'string', 'x-options' => 'users', 'x-label' => 'core:common.save']],
]);

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
        ->toEqual(['component' => 'reminders', 'keys' => ['lead_days'], 'changes' => ['lead_days' => ['from' => 3, 'to' => 7]]]);

    // Back to the default: the row goes, so a changed default applies later.
    $this->actingAs($admin)->put('/admin/settings/reminders', ['lead_days' => 3, 'channel' => 'mail']);
    expect(Setting::query()->count())->toBe(0);
});

it('keeps the organization language and time zone on the tenant', function () {
    $this->actingAs(settingsAdmin())->put('/admin/settings/core', [
        'default_locale' => 'en',
        'format_locale' => 'de-DE',
        'timezone' => 'Europe/Sarajevo',
        'shared_link_max_days' => 90, 'email_notifications' => '1',
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
        'shared_link_max_days' => 90, 'email_notifications' => '0',
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

describe('security review findings', function () {
    it('reads each tenant its own values when a job switches tenants', function () {
        app(Settings::class)->save('core', ['default_locale' => 'bs', 'format_locale' => 'en-US', 'timezone' => 'UTC', 'shared_link_max_days' => 90, 'email_notifications' => false], settingsAdmin());
        $settings = app(Settings::class);
        expect($settings->get('core.format_locale'))->toBe('en-US');

        app(TenantContext::class)->run(Tenant::factory()->create(), function () use ($settings) {
            // The same instance, no reset in between.
            expect($settings->get('core.format_locale'))->toBeNull()
                ->and($settings->get('core.email_notifications'))->toBeTrue();
        });
    });

    it('drops a module with a broken or escaping schema and keeps every page working', function (string $schema, array $files) {
        FakeModules::install(['broken' => ['settings_schema' => $schema]], ['broken' => $files]);
        app(TenantModules::class)->enable('broken');
        app()->forgetScopedInstances();
        $admin = settingsAdmin();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('groups', 1)->where('groups.0.component', 'core'));
    })->with([
        'invalid JSON' => ['settings.schema.json', ['settings.schema.json' => '{not json']],
        'unsupported keyword' => ['settings.schema.json', ['settings.schema.json' => '{"type":"object","properties":{"x":{"type":"string","format":"uri","x-label":"core:common.save"}}}']],
        'missing file' => ['missing.json', []],
        'outside the module' => ['../../../composer.json', []],
    ]);

    it('ignores input keys the schema does not declare', function () {
        $slug = $this->tenant->slug;

        $this->actingAs(settingsAdmin())->put('/admin/settings/core', [
            'default_locale' => 'bs', 'format_locale' => '', 'timezone' => 'UTC', 'shared_link_max_days' => 90, 'email_notifications' => '1',
            'slug' => 'taken-over', 'status' => 'suspended', 'edition' => 'enterprise',
        ])->assertSessionHasNoErrors();

        expect($this->tenant->refresh()->slug)->toBe($slug)
            ->and(Setting::query()->where('key', 'like', '%slug%')->exists())->toBeFalse();
    });

    it('accepts patterns with delimiters and refuses broken ones', function () {
        $schema = fn (string $pattern) => SettingsSchema::fromArray('reminders', ['type' => 'object', 'properties' => [
            'code' => ['type' => 'string', 'pattern' => $pattern, 'x-label' => 'core:common.save'],
        ]]);

        $definition = $schema('^a/b#c$')[0];
        expect(Validator::make(['code' => 'a/b#c'], ['code' => $definition->rules(null)])->passes())->toBeTrue()
            ->and(fn () => $schema('([unclosed'))->toThrow(InvalidArgumentException::class);
    });
});

describe('secret settings (S-0909)', function () {
    function mailModule(): void
    {
        FakeModules::install(
            ['mailer' => ['settings_schema' => 'settings.schema.json']],
            ['mailer' => ['settings.schema.json' => (string) json_encode([
                'type' => 'object',
                'properties' => [
                    'smtp_host' => ['type' => 'string', 'default' => 'localhost', 'x-label' => 'core:common.save'],
                    'smtp_password' => ['type' => 'string', 'x-secret' => true, 'x-label' => 'core:common.save'],
                ],
            ])]],
        );
        app(TenantModules::class)->enable('mailer');
        app()->forgetScopedInstances();
    }

    it('stores a secret encrypted, never sends it to the browser and keeps it when left empty', function () {
        mailModule();
        $admin = settingsAdmin();

        $this->actingAs($admin)->put('/admin/settings/mailer', ['smtp_host' => 'mail.example.test', 'smtp_password' => 'S3cret-Mail-Pass'])
            ->assertSessionHasNoErrors();

        $raw = DB::table('settings')->where('key', 'mailer.smtp_password')->value('value');
        expect($raw)->not->toContain('S3cret-Mail-Pass');
        app()->forgetScopedInstances();
        expect(app(Settings::class)->get('mailer.smtp_password'))->toBe('S3cret-Mail-Pass');

        $page = $this->actingAs($admin)->get('/admin/settings');
        expect($page->getContent())->not->toContain('S3cret-Mail-Pass');
        $page->assertInertia(fn (AssertableInertia $page) => $page
            ->where('groups.1.fields.1.value', null)
            ->where('groups.1.fields.1.isSet', true));

        // Empty keeps the stored secret.
        $this->actingAs($admin)->put('/admin/settings/mailer', ['smtp_host' => 'mail.example.test', 'smtp_password' => '']);
        app()->forgetScopedInstances();
        expect(app(Settings::class)->get('mailer.smtp_password'))->toBe('S3cret-Mail-Pass');

        // The audit names the setting, never its value.
        $audit = AuditEntry::query()->where('action', 'settings.changed')->get()->map->contextData();
        expect(json_encode($audit))->not->toContain('S3cret-Mail-Pass')->toContain('smtp_password');
    });

    it('never keeps a secret in the session when another field fails', function () {
        mailModule();

        $this->actingAs(settingsAdmin())
            ->put('/admin/settings/mailer', ['smtp_host' => ['not', 'a', 'string'], 'smtp_password' => 'S3cret-Mail-Pass'])
            ->assertSessionHasErrors('smtp_host');

        expect(json_encode(session()->all()))->not->toContain('S3cret-Mail-Pass');
    });

    it('refuses x-secret on settings that are not plain strings', function () {
        expect(fn () => SettingsSchema::fromArray('mailer', ['type' => 'object', 'properties' => [
            'pin' => ['type' => 'integer', 'x-secret' => true, 'x-label' => 'core:common.save'],
        ]]))->toThrow(InvalidArgumentException::class);
    });
});

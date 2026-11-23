<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Licensing\InstanceId;
use App\Licensing\InstanceLicense;
use App\Licensing\LicenseToken;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
 * S-0976: Admin > License, installed offline.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-02T10:00:00Z'));
    $this->file = sys_get_temp_dir().'/instance-id-'.bin2hex(random_bytes(6));
    app()->bind(InstanceId::class, fn () => new InstanceId($this->file));
    $this->pair = sodium_crypto_sign_keypair();
    config(['license-keys.keys' => [['kid' => 'k', 'public_key' => base64_encode(sodium_crypto_sign_publickey($this->pair)), 'valid_from' => '2026-11-01T00:00:00Z', 'valid_until' => '2027-12-31T00:00:00Z', 'status' => 'active']]]);
    $this->admin = licenseAdmin();
    $this->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);
});

afterEach(fn () => @unlink($this->file));

function licenseAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($admin, Role::query()->where('key', Role::ADMIN)->firstOrFail());

    return $admin;
}

function screenLicense(array $overrides = []): string
{
    return LicenseToken::encode(array_replace_recursive([
        'addons' => ['white_label'], 'features' => [],
        'binding' => ['domains' => ['localhost'], 'instance_id' => app(InstanceId::class)->get()],
        'customer' => ['id' => 'lc_1', 'name' => 'Primjer d.o.o.'],
        'edition' => 'business', 'plan' => 'founding_customer_business',
        'issued_at' => '2026-12-01T09:00:00Z', 'not_before' => '2026-12-01T00:00:00Z', 'expires_at' => '2027-12-01T00:00:00Z',
        'grace' => ['expiry_days' => 14], 'iss' => 'projektra-licensing', 'kid' => 'k',
        'license_id' => 'lic_01JD8QA2H6W4C8E0G2J4L6N8Q0', 'seats' => ['full' => 12, 'light' => 5],
        'serial' => 3, 'status' => 'active', 'v' => 1,
    ], $overrides), sodium_crypto_sign_secretkey(test()->pair));
}

it('shows Community, the instance id and the users in use', function () {
    $this->actingAs($this->admin)->get('/admin/license')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/License')
            ->where('license', null)
            ->where('status', null)
            ->where('instanceId', app(InstanceId::class)->get())
            ->where('used.full', DB::table('users')->where('user_type', 'full')->count())
            ->where('licenseAccess', true));
});

it('installs a license and shows what it includes', function () {
    $this->actingAs($this->admin)->post('/admin/license', ['license' => screenLicense()."\n"])->assertRedirect('/admin/license');
    app()->forgetInstance(InstanceLicense::class);

    $this->get('/admin/license')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('status', 'active')
        ->where('license.edition', 'business')
        ->where('license.full', 12)
        ->where('license.addons', ['white_label'])
        ->where('license.expiresAt', '2027-12-01T00:00:00Z')
        ->where('license.domainCovered', true));
    expect(DB::table('audit_log')->where('action', 'license.installed')->where('actor_id', $this->admin->id)->count())->toBe(1);
});

it('refuses with a plain reason and keeps the installed license', function (array $overrides, string $reason) {
    app(InstanceLicense::class)->install(screenLicense());

    $this->actingAs($this->admin)->from('/admin/license')->post('/admin/license', ['license' => screenLicense($overrides)])
        ->assertSessionHasErrors(['license' => __('core:license.refused.'.$reason)]);
    app()->forgetInstance(InstanceLicense::class);
    expect(app(InstanceLicense::class)->current()?->serial)->toBe(3)
        ->and(DB::table('audit_log')->where('action', 'license.refused')->count())->toBe(1);
})->with([
    'another installation' => [['binding' => ['instance_id' => 'inst_01JD8Q7Z5M3K9V2X4B6N8P0R1T']], 'other_instance'],
    'not started' => [['not_before' => '2027-01-01T00:00:00Z', 'serial' => 4], 'not_started'],
    'ended' => [['not_before' => '2025-01-01T00:00:00Z', 'expires_at' => '2026-01-01T00:00:00Z', 'serial' => 4], 'ended'],
    'older issue' => [['serial' => 2], 'older'],
    'unknown key' => [['kid' => 'other'], 'untrusted_key'],
    'content' => [['edition' => 'gold'], 'invalid'],
]);

it('has a text for every reason in every shipped language', function () {
    foreach (['en', 'bs'] as $locale) {
        $strings = (array) json_decode((string) file_get_contents(lang_path("{$locale}/core.json")), true);
        foreach (['invalid', 'untrusted_key', 'other_instance', 'not_started', 'ended', 'older'] as $reason) {
            expect(array_key_exists("license.refused.{$reason}", $strings))->toBeTrue();
        }
    }
});

it('refuses no license, a list and an oversized text', function (mixed $license) {
    $this->actingAs($this->admin)->post('/admin/license', ['license' => $license])->assertSessionHasErrors('license');
})->with(['nothing' => [null], 'empty' => [''], 'list' => [['x']], 'oversized' => [str_repeat('A', 20000)]]);

it('shows a changed license as invalid and another address as a warning', function () {
    app(InstanceLicense::class)->install(screenLicense(['binding' => ['domains' => ['other.test']]]));
    app()->forgetInstance(InstanceLicense::class);
    $this->actingAs($this->admin)->get('/admin/license')->assertInertia(fn (AssertableInertia $page) => $page->where('license.domainCovered', false));

    DB::table('instance_licenses')->update(['token' => 'PTKL1.e30.e30']);
    app()->forgetInstance(InstanceLicense::class);
    $this->get('/admin/license')->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'invalid')->where('license', null));
});

it('is closed to users without the permission', function () {
    $user = User::factory()->create();

    $this->get('/admin/license')->assertRedirect('/login');
    $this->actingAs($user)->get('/admin/license')->assertForbidden();
    $this->actingAs($user)->post('/admin/license', ['license' => screenLicense()])->assertForbidden();
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->where('licenseAccess', false));
    expect(DB::table('instance_licenses')->count())->toBe(0);
});

it('is closed to administrators of other tenants', function () {
    Tenant::factory()->create(['slug' => 'operator']);
    config(['doctor.operator_tenant' => 'operator']);

    $this->actingAs($this->admin)->get('/admin/license')->assertNotFound();
    $this->actingAs($this->admin)->post('/admin/license', ['license' => screenLicense()])->assertNotFound();
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->where('licenseAccess', false));

    expect(DB::table('instance_licenses')->count())->toBe(0)
        ->and(DB::table('audit_log')->where('action', 'like', 'license.%')->count())->toBe(0);

    // Several tenants and no operator named: nobody.
    config(['doctor.operator_tenant' => '']);
    $this->actingAs($this->admin)->get('/admin/license')->assertNotFound();
});

it('asks for the password again and installs nothing before', function () {
    $this->withSession(['auth.password_confirmed_at' => now()->subHours(1)->getTimestamp()]);

    $this->actingAs($this->admin)->get('/admin/license')->assertRedirect(route('password.confirm'));
    $this->actingAs($this->admin)->post('/admin/license', ['license' => screenLicense()])->assertRedirect(route('password.confirm'));
    expect(DB::table('instance_licenses')->count())->toBe(0);
});

it('installs nothing when the audit entry cannot be written', function () {
    DB::connection()->beforeExecuting(function (string $query): void {
        if (str_contains($query, 'audit_log') && str_starts_with($query, 'insert')) {
            throw new RuntimeException('audit down');
        }
    });

    $this->actingAs($this->admin)->post('/admin/license', ['license' => screenLicense()])->assertServerError();
    expect(DB::table('instance_licenses')->count())->toBe(0);
});

it('counts full and light users of every tenant for the operator tenant', function () {
    config(['doctor.operator_tenant' => $this->tenant->slug]);
    $other = Tenant::factory()->create();
    app(TenantContext::class)->run($other, fn () => User::factory()->count(3)->create());
    foreach (['full', 'light', 'portal'] as $i => $type) {
        DB::table('users')->where('id', DB::table('users')->where('tenant_id', $other->id)->orderBy('id')->pluck('id')[$i])->update(['user_type' => $type]);
    }

    $this->actingAs($this->admin)->get('/admin/license')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('used.full', 2)
        ->where('used.light', 1));
});

it('shows a damaged instance id and refuses to install on it', function () {
    app(InstanceId::class)->get();
    $license = screenLicense();
    DB::table('instance_state')->update(['instance_id' => 'broken']);

    $this->actingAs($this->admin)->get('/admin/license')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('instanceId', null));
    $this->post('/admin/license', ['license' => $license])->assertSessionHasErrors('license');
    expect(DB::table('instance_licenses')->count())->toBe(0);
});

it('names the earlier end of a pending payment', function () {
    app(InstanceLicense::class)->install(screenLicense(['status' => 'pending_payment', 'status_until' => '2026-12-15T23:59:59Z']));
    app()->forgetInstance(InstanceLicense::class);

    $this->actingAs($this->admin)->get('/admin/license')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('status', 'pending_payment')
        ->where('license.readOnlyFrom', '2026-12-16T00:00:00Z'));
});

it('refuses a license with a broken signature', function () {
    [$prefix, $payload] = explode('.', screenLicense());

    $this->actingAs($this->admin)->post('/admin/license', ['license' => $prefix.'.'.$payload.'.'.explode('.', screenLicense(['serial' => 9]))[2]])
        ->assertSessionHasErrors(['license' => __('core:license.refused.invalid')]);
});

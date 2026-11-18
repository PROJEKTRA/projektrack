<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantModule;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\TenantIsolation;

/*
 * S-0902: data of one tenant is never visible to, or writable by, another.
 * The test host "localhost" serves $this->tenant; "other.test" serves a second tenant.
 */

TenantIsolation::for(User::class, fn () => User::factory()->create());
TenantIsolation::for(TenantModule::class, fn () => TenantModule::query()->create(['module_key' => 'crm', 'enabled' => true]));

beforeEach(function () {
    $this->other = Tenant::factory()->withDomain('other.test')->create();
    $this->otherUser = app(TenantContext::class)->run($this->other, fn () => User::factory()->create([
        'email' => 'shared@example.test',
    ]));
});

it('signs a user in only on the host of their own tenant', function () {
    $this->post('http://other.test/login', ['email' => 'shared@example.test', 'password' => 'password']);
    $this->assertAuthenticated();
    auth()->logout();

    $this->post('http://localhost/login', ['email' => 'shared@example.test', 'password' => 'password']);
    $this->assertGuest();
});

it('treats a session from another tenant as signed out', function () {
    $own = User::factory()->create(['email' => 'own@example.test']);

    $this->post('http://localhost/login', ['email' => 'own@example.test', 'password' => 'password']);
    $this->get('http://localhost/dashboard')->assertOk();

    // A real request builds a fresh guard; the test client otherwise keeps the user in memory.
    $this->app['auth']->forgetGuards();

    // Same session, other tenant's host: the user id does not exist in that tenant.
    $this->get('http://other.test/dashboard')->assertRedirect();
    expect(auth()->user())->toBeNull()
        ->and($own->exists)->toBeTrue();
});

it('answers unknown hosts with a plain 404 that reveals nothing', function () {
    $response = $this->get('http://unknown.test/login');

    $response->assertNotFound()
        ->assertHeader('X-Robots-Tag')
        ->assertHeader('Content-Security-Policy');

    expect($response->getContent())->toBe('Not Found')
        ->and($response->headers->get('Set-Cookie'))->toBeNull();
});

it('answers hosts of suspended tenants like unknown hosts', function () {
    $this->other->update(['status' => 'suspended']);

    $this->get('http://other.test/login')->assertNotFound();
});

it('keeps the health check reachable without a tenant', function () {
    $this->get('http://unknown.test/up')->assertOk();
});

it('lets the same e-mail address exist once per tenant', function () {
    $own = User::factory()->create(['email' => 'shared@example.test']);

    expect($own->tenant_id)->not->toBe($this->otherUser->tenant_id);

    expect(fn () => User::factory()->create(['email' => 'shared@example.test']))
        ->toThrow(QueryException::class);
});

it('never accepts a password reset token from another tenant', function () {
    Notification::fake();
    $own = User::factory()->create(['email' => 'shared@example.test']);

    // Token issued for the other tenant's account with the same address.
    $token = app(TenantContext::class)->run($this->other, fn () => Password::broker()->createToken($this->otherUser));

    $this->post('http://localhost/reset-password', [
        'token' => $token,
        'email' => 'shared@example.test',
        'password' => 'N3w-passw0rd!long',
        'password_confirmation' => 'N3w-passw0rd!long',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $own->fresh()->password))->toBeTrue();

    // Requesting a link here leaves the other tenant's token untouched.
    $this->post('http://localhost/forgot-password', ['email' => 'shared@example.test']);
    Notification::assertSentTo($own, ResetPasswordLink::class);
    expect(DB::table('password_reset_tokens')->where('tenant_id', $this->other->id)->count())->toBe(1);
});

it('blocks cross-tenant references in the database itself', function () {
    // Bypass the application layer: a row of this tenant pointing at a user of another tenant.
    expect(fn () => DB::table('tenant_modules')->insert([
        'id' => (string) str()->ulid(),
        'tenant_id' => $this->tenant->id,
        'module_key' => 'crm',
        'enabled' => true,
        'changed_by' => $this->otherUser->id,
    ]))->toThrow(QueryException::class);
});

it('clears the tenant before a queued job runs', function () {
    expect(app(TenantContext::class)->has())->toBeTrue();

    $job = new SyncJob(app(), (string) json_encode(['job' => 'none', 'data' => [], 'uuid' => 'test']), 'sync', 'default');
    event(new JobProcessing('sync', $job));

    expect(app(TenantContext::class)->has())->toBeFalse();
});

it('restores the previous tenant after running code for another tenant', function () {
    $context = app(TenantContext::class);

    expect(fn () => $context->run($this->other, fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class);

    expect($context->id())->toBe($this->tenant->id);
});

it('resolves domains only through the resolver', function () {
    expect(TenantDomain::query()->pluck('host')->all())->toBe(['localhost']);
});

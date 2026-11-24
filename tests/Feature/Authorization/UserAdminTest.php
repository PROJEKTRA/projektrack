<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\Permissions;
use App\Authorization\RoleManager;
use App\I18n\LanguageChangeRefused;
use App\I18n\StringCatalog;
use App\I18n\TenantLanguages;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/*
 * S-0931: invitations and roles.
 */

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($this->admin, userRole('admin'));
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
});

function userRole(string $key): Role
{
    return Role::query()->where('key', $key)->firstOrFail();
}

it('invites a person with a role and a link, never a password', function () {
    $this->post('/admin/users', ['name' => 'New Person', 'email' => 'new@example.test', 'roles' => [userRole('employee')->id]])->assertSessionHasNoErrors();
    $person = User::query()->where('email', 'new@example.test')->sole();

    Notification::assertSentTo($person, ResetPasswordLink::class);
    expect(AuditEntry::query()->where('action', 'users.invited')->sole()->contextData())->toBe(['roles' => ['employee']]);
    $this->get('/admin/users')->assertInertia(fn (AssertableInertia $p) => $p->component('admin/Users')->has('users.data', 2));
    $this->post('/admin/users', ['name' => 'Again', 'email' => 'new@example.test', 'roles' => [userRole('employee')->id]])->assertSessionHasErrors('email');
    $this->post('/admin/users', ['name' => 'None', 'email' => 'none@example.test', 'roles' => []])->assertSessionHasErrors('roles');
});

it('stores the address as sign-in compares it', function () {
    $this->post('/admin/users', ['name' => 'Ana', 'email' => ' Ana@Example.test', 'roles' => [userRole('employee')->id]])->assertSessionHasNoErrors();
    $this->post('/admin/users', ['name' => 'Ana', 'email' => 'ana@example.test', 'roles' => [userRole('employee')->id]])->assertSessionHasErrors('email');
});

it('never lets the last administrator delete the own account', function () {
    $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
    expect(User::query()->whereKey($this->admin->id)->exists())->toBeTrue();
});

it('keeps legal texts out of language files without the permission', function () {
    $role = userRole('controller');
    app(RoleManager::class)->grant($role, ['core.strings.customize']);
    $editor = User::factory()->withTwoFactor()->create();
    app(RoleManager::class)->assign($editor, $role);
    $languages = app(TenantLanguages::class);
    $languages->copy('en', 'fr', 'Français', 'ltr', $this->admin);
    $id = 'invoices:legal_notice.reverse_charge';
    expect(fn () => $languages->import('fr', [$id => 'Changed'], $editor))->toThrow(LanguageChangeRefused::class);
    expect($languages->import('fr', [$id => app(StringCatalog::class)->message('en', $id)], $editor))->toBe(1);
    expect($languages->import('fr', ['core:common.save' => 'Enregistrer'], $editor))->toBe(1);
});

it('changes roles, but not the own ones', function () {
    $person = User::factory()->create();
    $this->put("/admin/users/{$person->id}", ['roles' => [userRole('controller')->id]])->assertSessionHasNoErrors();
    $this->put("/admin/users/{$person->id}", ['roles' => [userRole('employee')->id]])->assertSessionHasNoErrors();
    expect(app(Permissions::class)->roleIdsOf($person))->toBe([userRole('employee')->id]);
    $this->put("/admin/users/{$this->admin->id}", ['roles' => []])->assertForbidden();
});

it('never hands out or takes away more than the actor holds', function () {
    $role = userRole('project_lead');
    app(RoleManager::class)->grant($role, ['core.users.manage']);
    $lead = User::factory()->withTwoFactor()->create();
    app(RoleManager::class)->assign($lead, $role);
    $person = User::factory()->create();

    $this->actingAs($lead)->post('/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'roles' => [userRole('admin')->id]])->assertForbidden();
    $this->put("/admin/users/{$person->id}", ['roles' => [userRole('admin')->id]])->assertForbidden();
    $this->put("/admin/users/{$this->admin->id}", ['roles' => []])->assertForbidden();
    $this->put("/admin/users/{$person->id}", ['roles' => [userRole('employee')->id]])->assertSessionHasNoErrors();
    $this->put("/admin/users/{$person->id}", ['roles' => [userRole('customer')->id]])->assertNotFound();
});

it('needs the permission, a fresh password and the own tenant', function () {
    $employee = User::factory()->create();
    app(RoleManager::class)->assign($employee, userRole('employee'));
    $this->actingAs($employee)->get('/admin/users')->assertForbidden();
    $this->post('/admin/users', [])->assertForbidden();

    $foreign = app(TenantContext::class)->run(Tenant::factory()->create(), fn () => User::factory()->create());
    $this->actingAs($this->admin)->put("/admin/users/{$foreign->id}", ['roles' => []])->assertNotFound();

    $this->travel(20)->minutes();
    $this->post('/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'roles' => [userRole('employee')->id]])->assertRedirect('/user/confirm-password');
});

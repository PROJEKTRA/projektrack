<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Auth\TwoFactor\RecoveryCodes;
use App\Auth\TwoFactor\RecoveryCodeUsed;
use App\Auth\TwoFactor\TwoFactorLoginRequest;
use App\Auth\TwoFactor\TwoFactorReset;
use App\Authorization\InvalidRoleChange;
use App\Authorization\RoleManager;
use App\Authorization\TwoFactorRequirement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TwoFactorWasReset;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

/*
 * S-0904: TOTP two-factor authentication enforced per role.
 */

beforeEach(function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }
});

function userWithRole(string $key, bool $enrolled = false): User
{
    $factory = User::factory();
    $user = ($enrolled ? $factory->withTwoFactor() : $factory)->create();
    app(RoleManager::class)->assign($user, Role::query()->where('key', $key)->firstOrFail());

    return $user;
}

function passwordConfirmed(): array
{
    return ['auth.password_confirmed_at' => time()];
}

describe('enforcement', function () {
    it('sends a controller without two-factor to the setup page from every screen', function (string $path) {
        $this->actingAs(userWithRole('controller'))
            ->get($path)
            ->assertRedirect(route('security.edit'));
    })->with(['/dashboard', '/settings/profile', '/settings/appearance']);

    it('lets the controller reach the setup steps', function () {
        $controller = userWithRole('controller');

        $this->actingAs($controller)->withSession(passwordConfirmed())->get('/settings/security')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('twoFactorRequired', true)
                ->where('twoFactorNotices.required', __('core:two_factor.required')));

        $this->actingAs($controller)->withSession(passwordConfirmed())->post('/user/two-factor-authentication')
            ->assertSessionHasNoErrors();

        expect($controller->fresh()?->two_factor_secret)->not->toBeNull();
    });

    it('answers API requests of a user who must enroll with 403', function () {
        $this->actingAs(userWithRole('admin'))->getJson('/dashboard')->assertForbidden();
    });

    it('lets an enrolled administrator through', function () {
        $this->actingAs(userWithRole('admin', enrolled: true))->get('/dashboard')->assertOk();
    });

    it('does not force two-factor on an employee', function () {
        $this->actingAs(userWithRole('employee'))->get('/dashboard')->assertOk();
    });

    it('requires two-factor as soon as a role grants a sensitive permission', function () {
        $user = userWithRole('employee');
        $requirement = app(TwoFactorRequirement::class);
        expect($requirement->reason($user))->toBeNull();

        app(RoleManager::class)->grant(Role::query()->where('key', 'employee')->firstOrFail(), ['core.users.manage']);

        expect($requirement->reason($user))->toBe('permission:core.users.manage');
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('security.edit'));
    });

    it('lets an admin extend the requirement to any role, but never lift it from admin or controller', function () {
        $roles = app(RoleManager::class);
        $employee = userWithRole('employee');

        $roles->setTwoFactorRequired(Role::query()->where('key', 'employee')->firstOrFail(), true);

        expect(app(TwoFactorRequirement::class)->reason($employee))->toBe('role:employee')
            ->and(fn () => $roles->setTwoFactorRequired(Role::query()->where('key', 'admin')->firstOrFail(), false))
            ->toThrow(InvalidRoleChange::class);
    });

    it('does not let a user switch two-factor off while a role requires it', function () {
        $admin = userWithRole('admin', enrolled: true);

        $this->actingAs($admin)->withSession(passwordConfirmed())
            ->delete('/user/two-factor-authentication')
            ->assertSessionHasErrors('two_factor');

        expect($admin->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();

        $employee = userWithRole('employee', enrolled: true);
        $this->actingAs($employee)->withSession(passwordConfirmed())->delete('/user/two-factor-authentication');

        expect($employee->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();
    });
});

describe('recovery codes', function () {
    it('creates ten codes, stores only hashes and shows them once', function () {
        $user = userWithRole('employee');

        $this->actingAs($user)->withSession(passwordConfirmed())->post('/user/two-factor-authentication');

        $codes = $this->actingAs($user)->withSession(passwordConfirmed())->getJson('/user/two-factor-recovery-codes')->assertOk()->json();
        $stored = (string) $user->fresh()?->two_factor_recovery_codes;

        expect($codes)->toHaveCount(RecoveryCodes::COUNT)
            ->and($user->fresh()?->recoveryCodes())->toBe(array_map(RecoveryCodes::hash(...), $codes));

        foreach ($codes as $code) {
            expect($stored)->not->toContain($code);
        }

        $this->actingAs($user)->withSession(passwordConfirmed())->getJson('/user/two-factor-recovery-codes')->assertExactJson([]);
    });

    it('replaces all codes when the user regenerates them', function () {
        $user = userWithRole('employee', enrolled: true);
        $old = $user->recoveryCodes();

        $this->actingAs($user)->withSession(passwordConfirmed())->post('/user/two-factor-recovery-codes');
        $new = $this->actingAs($user)->withSession(passwordConfirmed())->getJson('/user/two-factor-recovery-codes')->json();

        expect($new)->toHaveCount(RecoveryCodes::COUNT)
            ->and(array_intersect($old, $user->fresh()?->recoveryCodes() ?? []))->toBe([]);
    });

    it('accepts a recovery code once, removes it and announces it for the audit log', function () {
        Event::fake([RecoveryCodeUsed::class]);
        $user = User::factory()->withTwoFactor()->create();

        $this->withSession(['login.id' => $user->id, 'login.remember' => false, TwoFactorLoginRequest::STARTED => now()->getTimestamp()])
            ->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        expect($user->fresh()?->recoveryCodes())->toBe([]);
        Event::assertDispatched(RecoveryCodeUsed::class, fn (RecoveryCodeUsed $e) => $e->userId === $user->id && $e->remaining === 0);

        auth()->logout();

        $this->withSession(['login.id' => $user->id, 'login.remember' => false, TwoFactorLoginRequest::STARTED => now()->getTimestamp()])
            ->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-1']);

        $this->assertGuest();
    });
});

describe('admin reset', function () {
    it('requires a recent password confirmation', function () {
        $admin = userWithRole('admin', enrolled: true);
        $target = userWithRole('employee', enrolled: true);

        $this->actingAs($admin)->delete("/users/{$target->id}/two-factor")->assertRedirect(route('password.confirm'));

        expect($target->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();
    });

    it('removes the setup, signs the user out everywhere and tells them by e-mail', function () {
        Notification::fake();
        Event::fake([TwoFactorReset::class]);
        config(['session.driver' => 'database']);
        $admin = userWithRole('admin', enrolled: true);
        $target = userWithRole('controller', enrolled: true);
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->withSession(passwordConfirmed())
            ->delete("/users/{$target->id}/two-factor")
            ->assertRedirect();

        $fresh = $target->fresh();
        expect($fresh?->two_factor_secret)->toBeNull()
            ->and($fresh?->two_factor_confirmed_at)->toBeNull()
            ->and($fresh?->recoveryCodes())->toBe([])
            ->and(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse();

        Notification::assertSentTo($target, TwoFactorWasReset::class);
        Event::assertDispatched(TwoFactorReset::class, fn (TwoFactorReset $e) => $e->userId === $target->id && $e->actorId === $admin->id);

        // The controller role still requires two-factor: enrollment is forced again.
        $this->flushSession();
        $this->actingAs($fresh)->get('/dashboard')->assertRedirect(route('security.edit'));
    });

    it('is refused for someone who may do more', function () {
        $manager = userWithRole('employee', enrolled: true);
        $target = userWithRole('admin', enrolled: true);
        app(RoleManager::class)->grant(Role::query()->where('key', 'employee')->firstOrFail(), ['core.users.manage']);
        $equal = userWithRole('employee', enrolled: true);
        // The grant made it a full user.
        $manager->refresh();

        $this->actingAs($manager)->withSession(passwordConfirmed())
            ->delete("/users/{$target->id}/two-factor")->assertForbidden();
        expect($target->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();

        $this->from('/admin/users')->withSession(passwordConfirmed())->delete("/users/{$equal->id}/two-factor")->assertRedirect('/admin/users');
        expect($equal->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();
    });

    it('is refused without core.users.manage', function () {
        $employee = userWithRole('employee');
        $target = userWithRole('employee', enrolled: true);

        $this->actingAs($employee)->withSession(passwordConfirmed())
            ->delete("/users/{$target->id}/two-factor")
            ->assertForbidden();
    });

    it('cannot reach a user of another tenant', function () {
        $admin = userWithRole('admin', enrolled: true);
        $foreigner = app(TenantContext::class)->run(Tenant::factory()->create(), fn () => User::factory()->withTwoFactor()->create());

        $this->actingAs($admin)->withSession(passwordConfirmed())
            ->delete("/users/{$foreigner->id}/two-factor")
            ->assertNotFound();
    });

    it('writes the e-mail in the user\'s language', function () {
        $user = User::factory()->create(['name' => 'Ana Test']);
        $user->locale = 'bs';

        $mail = (new TwoFactorWasReset)->toMail($user);

        expect($mail->subject)->toContain('poništena')
            ->and($mail->greeting)->toBe('Zdravo Ana Test,');
    });
});

it('treats money, permissions and licensing as sensitive', function (string $permission, bool $sensitive) {
    expect(TwoFactorRequirement::isSensitive($permission))->toBe($sensitive);
})->with([
    ['core.roles.manage', true],
    ['core.license.manage', true],
    ['invoices.finalize', true],
    ['timesheets.view_rates', true],
    ['core.files.upload', false],
    ['timesheets.book', false],
]);

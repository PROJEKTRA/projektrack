<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Auth\AuthenticateUser;
use App\Auth\LoginThrottle;
use App\Auth\NotCommonPassword;
use App\Auth\Sessions;
use App\Auth\TwoFactor\TwoFactorLoginRequest;
use App\Models\AuditEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

/*
 * S-0903: authentication and session security (security baseline section 2).
 */

const SIGN_IN_SECRET = 'JBSWY3DPEHPK3PXP';

function signInAttempt(string $email, string $password): TestResponse
{
    return test()->post(route('login.store'), ['email' => $email, 'password' => $password]);
}

/** First message of a field in the flashed error bag. */
function flashedError(string $field): ?string
{
    $errors = session('errors');

    if ($errors instanceof ViewErrorBag) {
        return $errors->first($field) ?: null;
    }

    return is_array($errors) ? ($errors['default']['messages'][$field][0] ?? null) : null;
}

function lastAuditAction(string $action): ?AuditEntry
{
    return AuditEntry::query()->where('action', $action)->orderByDesc('seq')->first();
}

describe('password storage and policy', function () {
    it('hashes with Argon2id at 64 MiB, time cost 4 and one thread', function () {
        $info = password_get_info(Hash::make('quiet river under amber light'));

        expect($info['algoName'])->toBe('argon2id')
            ->and($info['options'])->toBe(['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]);
    });

    it('rehashes an older hash with Argon2id at the next sign-in', function () {
        $user = User::factory()->create();
        // As an import would write it, past the model's hashing cast.
        DB::table('users')->where('id', $user->id)->update(['password' => password_hash('password', PASSWORD_BCRYPT, ['cost' => 4])]);

        signInAttempt($user->email, 'password');

        $this->assertAuthenticatedAs($user);
        expect($user->refresh()->password)->toStartWith('$argon2id$');
    });

    it('accepts long passphrases and refuses short, too long and common passwords', function (string $password, bool $valid) {
        $validator = Validator::make(['password' => $password], ['password' => Password::default()]);

        expect($validator->passes())->toBe($valid);
    })->with([
        '12 characters' => ['quiet river!', true],
        'passphrase' => ['quiet river under amber light', true],
        '11 characters' => ['quiet river', false],
        '129 characters' => [str_repeat('ab1', 43), false],
        'listed' => ['Password1234', false],
        'one character' => ['aaaaaaaaaaaaaa', false],
        'repeated word' => ['passwordpassword', false],
        'straight run' => ['123456789012', false],
        'letter run' => ['abcdefghijklmn', false],
    ]);

    it('explains a short password in the language of the user', function () {
        $ana = User::factory()->create(['locale' => 'bs']);

        $this->actingAs($ana)
            ->from('/settings/security')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'kratka',
                'password_confirmation' => 'kratka',
            ])
            ->assertSessionHasErrors('password');

        expect(flashedError('password'))->toBe('Polje lozinka mora imati najmanje 12 znakova.');
    });

    it('explains a common password in the language of the user', function () {
        $ana = User::factory()->create(['locale' => 'bs']);

        $this->actingAs($ana)
            ->from('/settings/security')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'password1234',
                'password_confirmation' => 'password1234',
            ])
            ->assertSessionHasErrors(['password' => __('core:auth.password_common', [], 'bs')]);

        expect(NotCommonPassword::isCommon('Sarajevo1234'))->toBeTrue();
    });

    it('never asks an outside service unless the breach check is switched on', function () {
        expect(config('auth.password_breach_check'))->toBeFalse()
            ->and(json_encode(Password::default()))->not->toContain('uncompromised');
    });
});

describe('lockout and rate limiting', function () {
    it('locks an account after 5 failed attempts, even with the right password then', function () {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            signInAttempt($user->email, 'wrong password');
        }

        signInAttempt($user->email, 'password')
            ->assertSessionHasErrors(['email' => __('core:auth.locked', ['minutes' => 15])]);

        $this->assertGuest();
        expect(lastAuditAction('auth.lockout'))->not->toBeNull();
    });

    it('locks an unknown address the same way, so the lock reveals nothing', function () {
        $known = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            signInAttempt($known->email, 'wrong password');
            signInAttempt('nobody@example.test', 'wrong password');
        }

        signInAttempt($known->email, 'x');
        $knownError = flashedError('email');
        signInAttempt('nobody@example.test', 'x');
        $unknownError = flashedError('email');

        expect($knownError)->toBe($unknownError)
            ->and($knownError)->toBe(__('core:auth.locked', ['minutes' => 15]));
    });

    it('doubles every further lock up to 24 hours', function () {
        $throttle = app(LoginThrottle::class);
        $locks = [];

        foreach (range(1, 9) as $round) {
            foreach (range(1, 5) as $attempt) {
                $lock = $throttle->failed('ana@example.test');
            }
            $locks[] = $lock;
            $this->travel($lock + 1)->seconds();
        }

        expect($locks)->toBe([900, 1800, 3600, 7200, 14400, 28800, 57600, 86400, 86400]);
    });

    it('forgets failures after a successful sign-in', function () {
        $user = User::factory()->create();

        foreach (range(1, 4) as $attempt) {
            signInAttempt($user->email, 'wrong password');
        }
        signInAttempt($user->email, 'password');
        $this->post(route('logout'));
        signInAttempt($user->email, 'wrong password');

        signInAttempt($user->email, 'password');
        $this->assertAuthenticatedAs($user);
    });

    it('answers a wrong password and an unknown address with the same message', function () {
        $user = User::factory()->create();

        signInAttempt($user->email, 'wrong password');
        $known = flashedError('email');
        signInAttempt('nobody@example.test', 'wrong password');
        $unknown = flashedError('email');

        expect($known)->toBe($unknown)->toBe(__('core:auth.failed'));
    });

    it('lets an operator lift a lock, audited without the address', function () {
        $user = User::factory()->create();
        foreach (range(1, 5) as $attempt) {
            signInAttempt($user->email, 'wrong password');
        }

        $this->artisan('projektrack:auth:unlock', ['tenant' => $this->tenant->slug, 'email' => $user->email])->assertSuccessful();

        signInAttempt($user->email, 'password');
        $this->assertAuthenticatedAs($user);
        expect(lastAuditAction('auth.unlocked')?->contextData())->toBe([]);
    });
});

describe('lock review findings', function () {
    it('shares one lock between case and space variants of an address', function () {
        $user = User::factory()->create(['email' => 'ana@example.test']);

        foreach (['ana@example.test', 'ANA@example.test', ' Ana@Example.test ', 'ana@EXAMPLE.TEST', 'aNa@example.test'] as $variant) {
            signInAttempt($variant, 'wrong password');
        }

        signInAttempt('ana@example.test', 'password')->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('signs in only with the exact stored address, never a collation look-alike', function () {
        $user = User::factory()->create(['email' => 'ana@example.test']);
        $request = Request::create('/login', 'POST', ['email' => 'ána@example.test', 'password' => 'password']);

        // SQLite compares bytes; MySQL and MariaDB would find the user, the exact check refuses it.
        expect(app(AuthenticateUser::class)($request))->toBeNull();
    });

    it('refuses authenticator codes once the account is locked', function () {
        $user = User::factory()->create(['two_factor_secret' => encrypt(SIGN_IN_SECRET), 'two_factor_confirmed_at' => now()]);

        signInAttempt($user->email, 'password')->assertRedirect(route('two-factor.login'));
        foreach (range(1, 5) as $attempt) {
            $this->post(route('two-factor.login.store'), ['code' => '000000']);
        }

        // Past the per-minute limit of the challenge, still inside the 15 minute lock.
        $this->travel(61)->seconds();
        $this->post(route('two-factor.login.store'), ['code' => (new Google2FA)->getCurrentOtp(SIGN_IN_SECRET)]);

        $this->assertGuest();
        expect(session()->has('login.id'))->toBeFalse();
    });

    it('drops a pending two-factor challenge after 5 minutes', function () {
        $user = User::factory()->create(['two_factor_secret' => encrypt(SIGN_IN_SECRET), 'two_factor_confirmed_at' => now()]);

        signInAttempt($user->email, 'password');
        $this->travel(TwoFactorLoginRequest::CHALLENGE_SECONDS + 1)->seconds();

        $this->post(route('two-factor.login.store'), ['code' => (new Google2FA)->getCurrentOtp(SIGN_IN_SECRET)]);

        $this->assertGuest();
        expect(session()->has('login.id'))->toBeFalse();
    });

    it('keeps a lock inside its tenant', function () {
        $throttle = app(LoginThrottle::class);
        foreach (range(1, 5) as $attempt) {
            $throttle->failed('ana@example.test');
        }

        app(TenantContext::class)->run(Tenant::factory()->create(), function () {
            expect(app(LoginThrottle::class)->availableIn('ana@example.test'))->toBe(0);
        });
        expect($throttle->availableIn('ana@example.test'))->toBeGreaterThan(0);
    });

    it('lifts the lock when the owner resets the password', function () {
        Notification::fake();
        $user = User::factory()->create();
        foreach (range(1, 5) as $attempt) {
            signInAttempt($user->email, 'wrong password');
        }

        $this->post(route('password.email'), ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'quiet river under amber light',
                'password_confirmation' => 'quiet river under amber light',
            ]);

            return true;
        });

        signInAttempt($user->email, 'quiet river under amber light');
        $this->assertAuthenticatedAs($user);
    });

    it('limits remember me to 30 days', function () {
        expect(config('auth.guards.web.remember'))->toBe(43200);
    });
});

describe('re-authentication for sensitive actions', function () {
    it('accepts a current authenticator code instead of the password, once', function () {
        $user = User::factory()->create(['two_factor_secret' => encrypt(SIGN_IN_SECRET), 'two_factor_confirmed_at' => now()]);
        $code = (new Google2FA)->getCurrentOtp(SIGN_IN_SECRET);

        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => $code])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->withSession([])->post(route('password.confirm.store'), ['password' => $code])
            ->assertSessionHasErrors('password');
    });

    it('refuses a code from users without two-factor authentication and counts failures', function () {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($user)->post(route('password.confirm.store'), ['password' => '123456'])
                ->assertSessionHasErrors('password');
        }

        // Locked now: even the right password is refused for 15 minutes.
        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertSessionHasErrors('password');
    });

    it('asks for a recent confirmation on every sensitive route', function (string $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain(RequirePassword::class);
    })->with(['security.edit', 'sessions.destroy', 'users.two-factor.reset']);
});

describe('session handling', function () {
    it('uses HttpOnly, SameSite=Lax cookies, Secure with the __Host- prefix over HTTPS', function () {
        $_SERVER['APP_URL'] = $_ENV['APP_URL'] = 'https://projektrack.example';
        $https = require base_path('config/session.php');
        $_SERVER['APP_URL'] = $_ENV['APP_URL'] = 'http://localhost';
        $http = require base_path('config/session.php');
        unset($_SERVER['APP_URL'], $_ENV['APP_URL']);

        expect($https['secure'])->toBeTrue()
            ->and($https['cookie'])->toBe('__Host-sid')
            ->and($https['http_only'])->toBeTrue()
            ->and($https['same_site'])->toBe('lax')
            ->and($https['domain'])->toBeNull()
            ->and($https['path'])->toBe('/')
            ->and($http['secure'])->toBeFalse()
            ->and($http['cookie'])->toBe('sid');
    });

    it('regenerates the session ID on sign-in and invalidates the session on sign-out', function () {
        $user = User::factory()->create();
        $this->get(route('login'));
        $before = session()->getId();

        signInAttempt($user->email, 'password');
        $signedIn = session()->getId();
        session()->put('marker', 'kept until sign-out');

        $this->post(route('logout'));

        expect($signedIn)->not->toBe($before)
            ->and(session()->getId())->not->toBe($signedIn)
            ->and(session()->has('marker'))->toBeFalse();
    });

    it('signs out other sessions and keeps the current one', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time(), Sessions::VERSION => 1, Sessions::STARTED => now()->getTimestamp()])
            ->from('/settings/security')
            ->delete(route('sessions.destroy'))
            ->assertRedirect('/settings/security');

        expect($user->refresh()->session_version)->toBe(2)
            ->and(session(Sessions::VERSION))->toBe(2)
            ->and(lastAuditAction('auth.sessions_ended'))->not->toBeNull();

        // The current session goes on ...
        $this->get('/dashboard')->assertOk();

        // ... another one, still on version 1, is signed out.
        $this->flushSession();
        $this->actingAs($user)->withSession([Sessions::VERSION => 1, Sessions::STARTED => now()->getTimestamp()])
            ->get('/dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('ends a session after the absolute lifetime, however active', function () {
        $user = User::factory()->create();
        $started = now()->getTimestamp() - (int) config('session.absolute_lifetime') * 60 - 1;

        $this->actingAs($user)->withSession([Sessions::VERSION => 1, Sessions::STARTED => $started])
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    });

    it('ends every session when the password is reset or changed', function () {
        $user = User::factory()->create();
        Notification::fake();

        $this->post(route('password.email'), ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'quiet river under amber light',
                'password_confirmation' => 'quiet river under amber light',
            ])->assertSessionHasNoErrors();

            return true;
        });
        expect($user->refresh()->session_version)->toBe(2);

        $this->actingAs($user)->withSession([Sessions::VERSION => 2, Sessions::STARTED => now()->getTimestamp()])
            ->put('/settings/password', [
                'current_password' => 'quiet river under amber light',
                'password' => 'amber light over a quiet river',
                'password_confirmation' => 'amber light over a quiet river',
            ])
            ->assertSessionHasNoErrors();

        expect($user->refresh()->session_version)->toBe(3)
            ->and(session(Sessions::VERSION))->toBe(3);
    });
});

describe('password reset', function () {
    it('answers known and unknown addresses identically', function () {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->from('/forgot-password')->post(route('password.email'), ['email' => $user->email]);
        $unknown = $this->from('/forgot-password')->post(route('password.email'), ['email' => 'nobody@example.test']);
        // A second request for the known address within the throttle window.
        $again = $this->from('/forgot-password')->post(route('password.email'), ['email' => $user->email]);

        foreach ([$known, $unknown, $again] as $response) {
            $response->assertRedirect('/forgot-password')
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', __('core:passwords.sent'));
        }
    });

    it('sends a single-use link valid for 60 minutes from the queue', function () {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user) {
            $reset = fn () => $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'quiet river under amber light',
                'password_confirmation' => 'quiet river under amber light',
            ]);

            $reset()->assertSessionHasNoErrors();
            $reset()->assertSessionHasErrors('email');

            return true;
        });

        expect(new ResetPasswordLink('token'))->toBeInstanceOf(ShouldQueue::class)
            ->and(config('auth.passwords.users.expire'))->toBe(60);
    });
});

it('translates the messages of the sign-in package', function () {
    expect(__('The provided two factor authentication code was invalid.', [], 'bs'))->toBe(__('core:two_factor.code_invalid', [], 'bs'))
        ->and(__('The provided two factor recovery code was invalid.', [], 'bs'))->toBe(__('core:two_factor.recovery_code_invalid', [], 'bs'))
        ->and(__('The provided password was incorrect.', [], 'bs'))->toBe(__('core:auth.password', [], 'bs'));
});

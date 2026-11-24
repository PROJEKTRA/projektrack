<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\EmailWasChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_a_new_email_needs_the_password_and_the_old_address_is_told()
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);
        $change = fn (array $more) => $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'new@example.com', ...$more]);

        $change([])->assertSessionHasErrors('current_password');
        $change(['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertSame('old@example.com', $user->refresh()->email);
        Notification::assertNothingSent();

        $change(['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->refresh()->email);
        Notification::assertSentOnDemand(EmailWasChanged::class, fn ($n, $channels, $to): bool => $to->routes === ['mail' => 'old@example.com'] && $n->toMail()->subject !== '');
    }

    public function test_a_failing_mail_server_does_not_break_the_change()
    {
        $user = User::factory()->create();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp'));

        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'new@example.com', 'current_password' => 'password'])->assertRedirect(route('profile.edit'));
        $this->assertSame('new@example.com', $user->refresh()->email);
    }

    public function test_guessing_the_password_locks_the_account()
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->delete(route('profile.destroy'), ['password' => "wrong-{$i}"])->assertSessionHasErrors('password');
        }

        // Even the right one no longer tells anything.
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNotNull($user->fresh());
    }

    public function test_signed_in_pages_are_not_stored_by_the_browser()
    {
        $this->actingAs(User::factory()->create())->get(route('profile.edit'))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('encryptHistory');
    }

    public function test_email_is_stored_in_lowercase_so_the_user_can_still_log_in()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Test@Example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('test@example.com', $user->refresh()->email);

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => 'Test@Example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_must_be_unique_regardless_of_case()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Taken@Example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}

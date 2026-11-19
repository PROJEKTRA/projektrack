<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Models\AppNotification;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Notifications\AppNotice;
use App\Notifications\InAppChannel;
use App\Notifications\TwoFactorWasReset;
use App\Settings\Settings;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeModules;

/*
 * S-0912: in-app and e-mail notifications in the recipient's language, with
 * e-mail per type chosen by each user.
 */

/** Stands in for "a week was submitted for approval" until time tracking exists. */
class WeekSubmitted extends AppNotice
{
    public function __construct(private readonly string $employee) {}

    public function type(): string
    {
        return 'timesheets.week_submitted';
    }

    public function message(): string
    {
        return 'core:strings.title';
    }

    public function arguments(): array
    {
        return ['name' => $this->employee];
    }

    public function url(): ?string
    {
        return '/dashboard';
    }
}

function timesheetsModule(): void
{
    FakeModules::install(['timesheets' => ['notifications' => [
        ['type' => 'timesheets.week_submitted', 'label_string' => 'core:nav.dashboard'],
        ['type' => 'other_module.sneaky', 'label_string' => 'core:nav.dashboard'],
    ]]]);
    app(TenantModules::class)->enable('timesheets');
    app()->forgetScopedInstances();
}

it('notifies the approver in-app and by e-mail in the approver language', function () {
    timesheetsModule();
    $approver = User::factory()->create(['locale' => 'bs', 'name' => 'Amra']);

    $approver->notify(new WeekSubmitted('Ana'));

    $stored = AppNotification::query()->sole();
    expect($stored->user_id)->toBe($approver->id)
        ->and($stored->type)->toBe('timesheets.week_submitted')
        ->and($stored->data['message'])->toBe('core:strings.title');

    $mail = (new WeekSubmitted('Ana'))->toMail($approver);
    $html = (string) $mail->render();
    expect($html)->toContain(e(__('core:strings.title', ['name' => 'Ana'], 'bs')))
        ->toContain(e(__('core:notifications.mail.greeting', ['name' => 'Amra'], 'bs')))
        ->and($mail->subject)->toBe(__('core:nav.dashboard', [], 'bs'));
});

it('sends e-mails without tracking pixels and with links exactly as given', function () {
    timesheetsModule();
    $approver = User::factory()->create();

    $html = (string) (new WeekSubmitted('Ana'))->toMail($approver)->render();
    preg_match_all('/href="([^"]+)"/', $html, $links);

    // Only links into the application, unchanged: the action and the header.
    expect($html)->not->toContain('<img')
        ->and($links[1])->toContain(url('/dashboard'))
        ->and(collect($links[1])->every(fn (string $link): bool => in_array($link, [url('/dashboard'), config('app.url')], true)))->toBeTrue();

    // Whatever the instance is called, the header never loads a picture.
    config(['app.name' => 'Laravel']);
    expect((string) (new WeekSubmitted('Ana'))->toMail($approver)->render())->not->toContain('<img');
});

it('lets each user turn e-mail off per type, never for security notices', function () {
    timesheetsModule();
    $user = User::factory()->create();

    $this->actingAs($user)->put('/settings/notifications', ['mail' => [
        'timesheets.week_submitted' => false,
        'core.two_factor_reset' => false,
    ]])->assertSessionHasNoErrors();

    expect((new WeekSubmitted('Ana'))->via($user))->toBe([InAppChannel::class])
        ->and((new TwoFactorWasReset)->via($user))->toBe([InAppChannel::class, 'mail']);

    $this->actingAs($user)->get('/settings/notifications')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/Notifications')
            ->where('types', fn ($types) => collect($types)->firstWhere('type', 'timesheets.week_submitted')['mail'] === false
                && collect($types)->firstWhere('type', 'core.two_factor_reset')['mail'] === true
                // A module declares types only under its own key.
                && collect($types)->doesntContain('type', 'other_module.sneaky')));
});

it('sends only security notices by e-mail when the organization turned e-mail off', function () {
    timesheetsModule();
    $user = User::factory()->create();
    app(Settings::class)->save('core', [
        'default_locale' => 'bs', 'format_locale' => null, 'timezone' => 'UTC', 'email_notifications' => false,
    ], User::factory()->create());
    app()->forgetScopedInstances();

    expect((new WeekSubmitted('Ana'))->via($user))->toBe([InAppChannel::class])
        ->and((new TwoFactorWasReset)->via($user))->toBe([InAppChannel::class, 'mail']);
});

it('lists the own notifications in the reader language, opens and marks them read', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $other = User::factory()->create();
    $user->notify(new TwoFactorWasReset);
    $other->notify(new TwoFactorWasReset);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->where('unreadNotifications', 1));

    $mine = AppNotification::query()->where('user_id', $user->id)->sole();
    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('notifications/Index')
            ->has('notifications', 1)
            ->where('notifications.0.message', __('core:two_factor.reset_notice', [], 'en'))
            ->where('notifications.0.read', false));

    $this->actingAs($user)->post("/notifications/{$mine->id}/open")->assertRedirect('/settings/security');
    expect($mine->refresh()->read_at)->not->toBeNull();

    // Someone else's notification stays closed.
    $theirs = AppNotification::query()->where('user_id', $other->id)->sole();
    $this->actingAs($user)->post("/notifications/{$theirs->id}/open")->assertNotFound();
});

it('marks all as read', function () {
    $user = User::factory()->create();
    $user->notify(new TwoFactorWasReset);
    $user->notify(new TwoFactorWasReset);

    $this->actingAs($user)->post('/notifications/read');

    expect(AppNotification::query()->whereNull('read_at')->count())->toBe(0);
});

it('never leads off the site from a notification', function () {
    $user = User::factory()->create();
    $note = AppNotification::query()->create([
        'user_id' => $user->id, 'type' => 'core.two_factor_reset',
        'data' => ['message' => 'core:two_factor.reset_notice', 'args' => [], 'url' => '//evil.example/x'],
    ]);

    $this->actingAs($user)->post("/notifications/{$note->id}/open")->assertRedirect(route('notifications.index'));
});

it('stores a notification in the recipient tenant', function () {
    $foreign = Tenant::factory()->create();
    $recipient = app(TenantContext::class)->run($foreign, fn () => User::factory()->create());

    Notification::sendNow($recipient, new TwoFactorWasReset);

    expect(AppNotification::query()->withoutGlobalScopes()->where('user_id', $recipient->id)->value('tenant_id'))->toBe($foreign->id);
});

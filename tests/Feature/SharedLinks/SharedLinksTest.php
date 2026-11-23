<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Http\Middleware\SetSecurityHeaders;
use App\Models\SharedLink;
use App\Models\SharedLinkAccess;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\Settings;
use App\SharedLinks\SharedLinks;
use App\SharedLinks\SharedLinkTarget;
use App\SharedLinks\SharedLinkTargets;
use App\Support\RedactSecrets;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Symfony\Component\HttpFoundation\Response;

/*
 * S-0987: secure shared links (security baseline section 7).
 */

/** A shareable object type standing in for a module's, e.g. service reports. */
class FakeReportTarget implements SharedLinkTarget
{
    /** @var array<string, string> file id => owner user id */
    public array $owners = [];

    public string $module = 'core';

    public function type(): string
    {
        return 'fake_report';
    }

    public function module(): string
    {
        return $this->module;
    }

    public function actions(): array
    {
        return ['view' => false, 'download' => false, 'sign_off' => true];
    }

    public function find(string $id): ?Model
    {
        // Deliberately without the tenant scope: the core must catch a foreign object.
        return StoredFile::withoutGlobalScopes()->find($id);
    }

    public function canShare(User $user, Model $object): bool
    {
        return ($this->owners[$object->getKey()] ?? null) === $user->id;
    }

    public function respond(SharedLink $link, Model $object, string $action, Request $request): Response
    {
        return response("Report {$object->getKey()}: {$action}");
    }
}

function fakeReport(): StoredFile
{
    return StoredFile::query()->create([
        'disk' => 'local', 'storage_key' => Str::random(20), 'original_name' => 'report.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 1, 'sha256' => str_repeat('0', 64), 'scan_status' => 'skipped', 'context' => 'attachments',
    ]);
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->report = fakeReport();
    $this->target = new FakeReportTarget;
    $this->target->owners[$this->report->id] = $this->owner->id;
    app(SharedLinkTargets::class)->register($this->target);
    $this->links = app(SharedLinks::class);
});

function share(array $actions = ['view'], int $days = 30, ?string $password = null): array
{
    return test()->links->create(test()->owner, 'fake_report', test()->report->id, $actions, $days, $password);
}

/** The page without the nonce, which differs per response. */
function pageOf(TestResponse $response): string
{
    return (string) preg_replace('/nonce="[^"]+"/', '', (string) $response->getContent());
}

it('creates an unguessable token that is stored only as a hash', function () {
    ['link' => $link, 'token' => $token] = share();

    expect($token)->toMatch('/^'.SharedLinks::PATTERN.'$/')
        // 32 random bytes: 256 bits.
        ->and(strlen(base64_decode(strtr(substr($token, 4), '-_', '+/'))))->toBe(32)
        ->and($link->token)->toBe(hash('sha256', $token))
        ->and($link->expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($link->actions)->toBe(['view']);

    $this->get("/s/{$token}")->assertOk();

    // The token appears nowhere in the database or the session.
    foreach (['shared_links', 'shared_link_accesses', 'audit_log'] as $table) {
        expect(json_encode(DB::table($table)->get()))->not->toContain(substr($token, 4));
    }
    expect(json_encode(session()->all()))->not->toContain(substr($token, 4));
});

it('caps the validity at the tenant limit', function () {
    $settings = app(Settings::class);
    $settings->save('core', [...$settings->values('core'), 'shared_link_max_days' => 10], $this->owner);

    expect(share(days: 60)['link']->expires_at->isSameDay(now()->addDays(10)))->toBeTrue();
});

it('lets only people who may share the object create a link', function () {
    $this->links->create(User::factory()->create(), 'fake_report', $this->report->id);
})->throws(AuthorizationException::class);

it('refuses to share an object of another tenant', function () {
    $foreign = app(TenantContext::class)->run(Tenant::factory()->create(), fn () => fakeReport());
    $this->target->owners[$foreign->id] = $this->owner->id;

    $this->links->create($this->owner, 'fake_report', $foreign->id);
})->throws(AuthorizationException::class);

it('refuses weak link passwords', function () {
    share(password: 'short');
})->throws(InvalidArgumentException::class);

it('refuses a second target of the same type and one whose view changes things', function (SharedLinkTarget $target) {
    app(SharedLinkTargets::class)->register($target);
})->throws(InvalidArgumentException::class)->with([
    'duplicate' => [fn () => new FakeReportTarget],
    'view mutates' => [fn () => new class extends FakeReportTarget
    {
        public function type(): string
        {
            return 'other_report';
        }

        public function actions(): array
        {
            return ['view' => true];
        }
    }],
]);

it('shows the object to anyone with the link, uncached and unindexed, and logs the use', function () {
    ['link' => $link, 'token' => $token] = share();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
        ->withHeader('User-Agent', 'Mozilla/5.0 Firefox/140.0')
        ->get("/s/{$token}")
        ->assertOk()
        ->assertSee("Report {$this->report->id}: view")
        ->assertHeader('X-Robots-Tag', SetSecurityHeaders::ROBOTS)
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private');

    $access = SharedLinkAccess::query()->sole();
    expect($access->shared_link_id)->toBe($link->id)
        ->and($access->outcome)->toBe('ok')
        ->and($access->ip_prefix)->toBe('203.0.113.0')
        ->and($access->browser)->toBe('firefox')
        ->and($link->fresh()->last_accessed_at)->not->toBeNull();
});

it('shows link previews and mail scanners nothing', function () {
    $token = share()['token'];

    $this->withHeader('User-Agent', 'Slackbot-LinkExpanding 1.0')->get("/s/{$token}")
        ->assertOk()
        ->assertDontSee('Report')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('shared/Hidden'));
    $this->call('HEAD', "/s/{$token}")->assertOk()->assertContent('');

    expect(SharedLinkAccess::query()->pluck('outcome')->all())->toBe(['bot']);
});

it('answers every unusable link with the same 404', function (Closure $break) {
    ['link' => $link, 'token' => $token] = share();
    $unknown = $this->get('/s/shl_'.str_repeat('A', 43));

    $break($link, $this);

    expect(pageOf($this->get("/s/{$token}")->assertNotFound()))->toBe(pageOf($unknown));
})->with([
    'expired' => [fn (SharedLink $link) => $link->forceFill(['expires_at' => now()->subMinute()])->save()],
    'revoked' => [fn (SharedLink $link) => app(SharedLinks::class)->revoke($link, test()->owner)],
    'object gone' => [fn (SharedLink $link, $test) => $test->report->delete()],
    'module disabled' => [fn (SharedLink $link, $test) => $test->target->module = 'not_enabled'],
    'parent revoked' => [fn (SharedLink $link, $test) => app(SharedLinks::class)->revokeAllFor('fake_report', $test->report->id, $test->owner)],
]);

it('grants only the actions named on the link, by the right method', function () {
    $token = share(['download', 'sign_off'])['token'];

    $this->get("/s/{$token}/download")->assertOk()->assertSee('download');
    // Changing actions by POST with a CSRF token only, never by a link a scanner follows.
    $this->get("/s/{$token}/sign_off")->assertNotFound();
    $this->post("/s/{$token}/sign_off")->assertOk()->assertSee('sign_off');
    $this->post("/s/{$token}/download")->assertNotFound();
    $this->get("/s/{$token}/anything")->assertNotFound();

    $viewOnly = share()['token'];
    $this->get("/s/{$viewOnly}/download")->assertNotFound();
});

it('never resolves a link on another tenant\'s host', function () {
    $token = share()['token'];
    Tenant::factory()->withDomain('other.test')->create();

    $this->get("http://other.test/s/{$token}")->assertNotFound();
    $this->get("http://localhost/s/{$token}")->assertOk();
});

it('asks for the password before anything is shown', function () {
    ['link' => $link, 'token' => $token] = share(['download'], password: 'correct horse battery');

    $this->get("/s/{$token}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('shared/Unlock')->where('action', "/s/{$token}"));
    $this->get("/s/{$token}/download")->assertNotFound();

    $this->post("/s/{$token}", ['password' => 'wrong'])->assertRedirect("/s/{$token}")->assertSessionHasErrors('password');
    $this->post("/s/{$token}", ['password' => 'correct horse battery'])->assertRedirect("/s/{$token}");

    $this->get("/s/{$token}")->assertOk()->assertSee('view');
    $this->get("/s/{$token}/download")->assertOk();
    expect(SharedLinkAccess::query()->where('outcome', 'password_wrong')->count())->toBe(1);
});

it('asks again after a new password or after twelve hours', function () {
    ['link' => $link, 'token' => $token] = share(password: 'correct horse battery');
    $this->post("/s/{$token}", ['password' => 'correct horse battery']);
    $this->get("/s/{$token}")->assertSee('view');

    $link->forceFill(['password' => bcrypt('another good password')])->save();
    $this->get("/s/{$token}")->assertInertia(fn (AssertableInertia $page) => $page->component('shared/Unlock'));

    $this->post("/s/{$token}", ['password' => 'another good password']);
    $this->travel(13)->hours();
    $this->get("/s/{$token}")->assertInertia(fn (AssertableInertia $page) => $page->component('shared/Unlock'));
});

it('locks the link for an hour after ten wrong passwords, counting each attempt once', function () {
    ['link' => $link, 'token' => $token] = share(password: 'correct horse battery');

    foreach (range(1, SharedLinks::MAX_FAILED_PASSWORDS) as $attempt) {
        // A stale model, as parallel requests hold: the count still rises.
        expect($this->links->checkPassword($link, 'wrong'))->toBeFalse();
    }

    // Even the right password waits now.
    expect($this->links->checkPassword($link->fresh(), 'correct horse battery'))->toBeFalse();
    $this->post("/s/{$token}", ['password' => 'correct horse battery'])->assertStatus(429);
    $this->get("/s/{$token}")->assertStatus(429);
    expect(DB::table('audit_log')->where('action', 'shared_links.locked')->count())->toBe(1);

    // An attempt counted just before the lock is not checked after it.
    DB::table('shared_links')->where('id', $link->id)->update(['failed_attempts' => 0]);
    expect($this->links->checkPassword($link, 'correct horse battery'))->toBeFalse();

    $this->travel(61)->minutes();
    expect($this->links->checkPassword($link->fresh(), 'correct horse battery'))->toBeTrue();
});

it('keeps an unlocked visitor in while others are locked out', function () {
    ['link' => $link, 'token' => $token] = share(password: 'correct horse battery');
    $this->post("/s/{$token}", ['password' => 'correct horse battery']);

    $link->forceFill(['locked_until' => now()->addHour()])->save();

    $this->get("/s/{$token}")->assertOk()->assertSee('view');
});

it('limits requests per address', function () {
    $token = share()['token'];

    foreach (range(1, 60) as $i) {
        $this->get("/s/{$token}");
    }

    $this->get("/s/{$token}")->assertStatus(429);
});

it('keeps tokens out of every log', function () {
    $token = share()['token'];
    $handler = new TestHandler;
    $monolog = new Monolog('test', [$handler]);
    (new RedactSecrets)(new Logger($monolog));

    $monolog->error("GET /s/{$token} failed", [
        'url' => "https://work.example.com/s/{$token}",
        'nested' => ['secret' => 'ptk_'.str_repeat('x', 40)],
        'uri' => Str::of("/s/{$token}"),
    ]);

    $record = json_encode($handler->getRecords()[0]->toArray());
    expect($record)->not->toContain(substr($token, 4))->not->toContain(str_repeat('x', 40))->toContain('shl_[redacted]');
});

it('redacts tokens in the configured log channels and in reported exceptions', function () {
    $token = share()['token'];
    $file = storage_path('logs/redaction-'.Str::random(8).'.log');
    config(['logging.default' => 'single', 'logging.channels.single.path' => $file]);
    Log::forgetChannel('single');

    Log::error("GET /s/{$token}");
    report(new RuntimeException("Failed at https://work.example.com/s/{$token}"));

    $content = (string) file_get_contents($file);
    @unlink($file);
    expect($content)->toContain('shl_[redacted]')->not->toContain(substr($token, 4));

    // Every channel that writes somewhere has the tap.
    foreach (['single', 'daily', 'monthly', 'slack', 'papertrail', 'stderr', 'syslog', 'errorlog'] as $channel) {
        expect(config("logging.channels.{$channel}.tap"))->toContain(RedactSecrets::class);
    }
});

it('keeps only a shortened address and the browser family', function () {
    expect(SharedLinks::shortenIp('198.51.100.23'))->toBe('198.51.100.0')
        ->and(SharedLinks::shortenIp('::ffff:198.51.100.23'))->toBe('198.51.100.0')
        ->and(SharedLinks::shortenIp('2001:db8:1234:5678::1'))->toBe('2001:db8:1234::')
        ->and(SharedLinks::shortenIp('not an ip'))->toBeNull()
        ->and(SharedLinks::limitKey('2001:db8:1234:5678::1'))->toBe(SharedLinks::limitKey('2001:db8:1234:5678:ffff::9'))
        ->and(SharedLinks::browserFamily('Slackbot-LinkExpanding 1.0'))->toBe('bot')
        ->and(SharedLinks::browserFamily('Mozilla/5.0 Chrome/140.0 Safari/537.36 Edg/140.0'))->toBe('edge');
});

it('lists links and uses for the owner of the object', function () {
    ['link' => $link, 'token' => $token] = share();
    $this->get("/s/{$token}");

    expect($this->links->of('fake_report', $this->report->id))->toHaveCount(1)
        ->and($this->links->accesses($link))->toHaveCount(1);

    app(TenantContext::class)->run(Tenant::factory()->create(), function () {
        expect($this->links->of('fake_report', $this->report->id))->toBe([]);
    });
});

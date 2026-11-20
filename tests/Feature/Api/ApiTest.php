<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Api\ApiTokens;
use App\Api\OpenApiDocument;
use App\Auth\Sessions;
use App\Auth\TwoFactor\ResetTwoFactor;
use App\Authorization\Permissions;
use App\Authorization\RoleManager;
use App\Http\Middleware\GuardApiRequest;
use App\Models\ApiToken;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TwoFactorWasReset;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * S-0913: REST API with personal access tokens and an OpenAPI document.
 */

function apiUser(string $role = 'employee'): User
{
    $user = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    $model = Role::query()->where('key', $role)->firstOrFail();
    // An integration user: the role may use API tokens (admins grant this).
    app(RoleManager::class)->grant($model, ['core.api_tokens.manage']);
    app(RoleManager::class)->assign($user, $model);

    return $user;
}

/** @param list<string> $scopes */
function tokenFor(User $user, array $scopes, int $days = 90): string
{
    return app(ApiTokens::class)->create($user, 'test', $scopes, $days)['secret'];
}

describe('tokens', function () {
    it('creates a scoped token in the settings, shows it once and stores only its hash', function () {
        $admin = apiUser('admin');

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from('/settings/api-tokens')
            ->post('/settings/api-tokens', ['name' => 'Payroll export', 'scopes' => ['core:read'], 'days' => 30])
            ->assertSessionHasNoErrors();

        $token = ApiToken::query()->sole();
        // The secret travels once, in the flash data of the next page.
        $page = $this->actingAs($admin)->get('/settings/api-tokens');
        preg_match('/[0-9a-z]{26}\|ptk_[A-Za-z0-9]+/', (string) $page->getContent(), $found);
        $secret = $found[0] ?? null;

        expect($secret)->toBeString()->toStartWith($token->id.'|ptk_')
            ->and($token->token)->toBe(hash('sha256', explode('|', $secret, 2)[1]))
            ->and($token->token)->not->toContain('ptk_')
            ->and($token->abilities)->toBe(['core:read'])
            ->and($token->expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
            ->and(json_encode(AuditEntry::query()->where('action', 'api_tokens.created')->sole()->contextData()))->not->toContain('ptk_');

        // Never again, and the page is not kept in caches.
        $plain = explode('|', $secret, 2)[1];
        expect($page->headers->get('Cache-Control'))->toContain('no-store');
        $page = $this->actingAs($admin)->get('/settings/api-tokens');
        expect((string) $page->getContent())->not->toContain($plain);
        $page->assertInertia(fn (AssertableInertia $p) => $p->component('settings/ApiTokens')->where('tokens.0.name', 'Payroll export'));
    });

    it('needs a recent confirmation, the permission, known scopes and at most a year', function () {
        $admin = apiUser('admin');

        $this->actingAs($admin)->post('/settings/api-tokens', ['name' => 'x', 'scopes' => ['core:read'], 'days' => 30])
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/settings/api-tokens', ['name' => 'x', 'scopes' => ['core:everything'], 'days' => 400])
            ->assertSessionHasErrors(['scopes.0', 'days']);

        $light = User::factory()->create();
        $this->actingAs($light)->get('/settings/api-tokens')->assertForbidden();
    });

    it('revokes a token, which then stops working', function () {
        $user = apiUser('admin');
        $secret = tokenFor($user, ['core:read']);
        $id = ApiToken::query()->sole()->id;

        $this->actingAs($user)->delete("/settings/api-tokens/{$id}")->assertRedirect();
        auth()->forgetGuards();

        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
    });
});

describe('scopes and permissions', function () {
    it('reads with a read token and nothing more', function () {
        $user = apiUser();
        $user->notify(new TwoFactorWasReset);
        $secret = tokenFor($user, ['core:read']);

        $this->withToken($secret)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('scopes', ['core:read']);
        $this->withToken($secret)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.message', __('core:two_factor.reset_notice', [], 'en'));

        $this->withToken($secret)->postJson('/api/v1/notifications/read')->assertForbidden();
        $this->withToken($secret)->getJson('/api/v1/settings/core')->assertForbidden();
    });

    it('applies the same permissions as the UI, whatever the token scope', function () {
        // core:admin scope, but the user may not manage settings.
        $secret = tokenFor(apiUser(), ['core:admin']);
        $this->withToken($secret)->getJson('/api/v1/settings/core')->assertForbidden();
        auth()->forgetGuards();

        $admin = apiUser('admin');
        $this->withToken(tokenFor($admin, ['core:admin']))->getJson('/api/v1/settings/core')
            ->assertOk()
            ->assertJsonPath('component', 'core')
            ->assertJsonPath('values.email_notifications', true);
    });

    it('refuses expired tokens, session cookies and missing tokens', function () {
        $user = apiUser();
        $secret = tokenFor($user, ['core:read'], 1);

        $this->travel(2)->days();
        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();

        // A signed-in browser session is not an API client.
        $this->actingAs($user)->getJson('/api/v1/me')->assertUnauthorized();
        auth()->forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('keeps a token inside its tenant', function () {
        $secret = tokenFor(apiUser(), ['core:read']);
        Tenant::factory()->withDomain('other.test')->create();

        $this->withToken($secret)->getJson('http://other.test/api/v1/me')->assertUnauthorized();
    });

    it('uploads and downloads files with the same rules as the UI', function () {
        Storage::fake('local');
        $user = apiUser();
        $write = tokenFor($user, ['core:write', 'core:read']);

        $id = $this->withToken($write)->post('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello'), 'context' => 'attachments'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('id');
        $this->withToken($write)->post('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('x.svg', '<svg/>'), 'context' => 'attachments'], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        expect($this->withToken($write)->get("/api/v1/files/{$id}")->assertOk()->streamedContent())->toBe('hello');

        // Another user's token: the file is not theirs to read.
        auth()->forgetGuards();
        $this->withToken(tokenFor(apiUser(), ['core:read']))->getJson("/api/v1/files/{$id}")->assertForbidden();
        expect(StoredFile::query()->count())->toBe(1);
    });

    it('limits each token to 60 requests a minute', function () {
        $secret = tokenFor(apiUser(), ['core:read']);

        foreach (range(1, 60) as $request) {
            $this->withToken($secret)->getJson('/api/v1/me')->assertOk();
        }

        $this->withToken($secret)->getJson('/api/v1/me')->assertTooManyRequests();
    });
});

describe('OpenAPI', function () {
    it('documents every API route and the committed document is current', function () {
        $document = app(OpenApiDocument::class);

        expect($document->undocumented())->toBe([])
            ->and((string) file_get_contents(base_path(OpenApiDocument::PATH)))->toBe($document->json());

        $spec = $document->build();
        expect($spec['openapi'])->toBe('3.1.0')
            ->and($spec['paths']['/api/v1/me']['get']['x-scope'])->toBe('core:read')
            ->and($spec['paths']['/api/v1/settings/{component}']['get']['x-permission'])->toBe('core.settings.manage');
    });

    it('fails the check for an undocumented route', function () {
        Route::middleware('api')->get('api/v1/_test/undocumented', fn () => 'x');

        expect(app(OpenApiDocument::class)->undocumented())->toContain('GET api/v1/_test/undocumented');
        $this->artisan('projektrack:api:openapi', ['--check' => true])->assertFailed();
    });
});

it('keeps tokens of another tenant out of reach in the database layer', function () {
    $foreign = Tenant::factory()->create();
    app(TenantContext::class)->run($foreign, fn () => tokenFor(User::factory()->create(), ['core:read']));

    expect(ApiToken::query()->count())->toBe(0);
});

describe('security review findings', function () {
    it('ends every token when the account is recovered', function (Closure $recover) {
        $user = apiUser('admin');
        $secret = tokenFor($user, ['core:read']);

        $recover($user);
        auth()->forgetGuards();

        expect(ApiToken::query()->count())->toBe(0);
        $this->withToken($secret)->getJson('/api/v1/me')->assertUnauthorized();
        expect(AuditEntry::query()->where('action', 'api_tokens.revoked_all')->exists())->toBeTrue();
    })->with([
        'password reset or other sessions ended' => [fn (User $user) => app(Sessions::class)->endAll($user)],
        'two-factor reset by an admin' => [fn (User $user) => app(ResetTwoFactor::class)($user, apiUser('admin'))],
    ]);

    it('refuses tokens of users who lost the right to use tokens or lack required two-factor', function () {
        $user = apiUser('admin');
        $secret = tokenFor($user, ['core:read']);

        // The role now requires two-factor and the user has none.
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->withToken($secret)->getJson('/api/v1/me')->assertForbidden();

        // Roles removed: no core.api_tokens.manage any more.
        auth()->forgetGuards();
        $employee = apiUser();
        $other = tokenFor($employee, ['core:read']);
        DB::table('user_roles')->where('user_id', $employee->id)->delete();
        app(Permissions::class)->flush();
        $this->withToken($other)->getJson('/api/v1/me')->assertForbidden();
    });

    it('refuses a route that declares no scope, and every route declares what the document says', function () {
        Route::middleware(['auth:sanctum', 'access:self', GuardApiRequest::class])->get('api/v1/_test/no-scope', fn () => 'open');
        $this->withToken(tokenFor(apiUser(), ['core:read']))->get('/api/v1/_test/no-scope')->assertForbidden();

        $spec = app(OpenApiDocument::class)->build();
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || str_contains($route->uri(), '_test')) {
                continue;
            }
            $operation = $spec['paths']['/'.$route->uri()][strtolower(array_values(array_diff($route->methods(), ['HEAD']))[0])];
            expect($route->gatherMiddleware())->toContain('scope:'.$operation['x-scope']);
            if (isset($operation['x-permission'])) {
                expect($route->gatherMiddleware())->toContain('permission:'.$operation['x-permission']);
            }
        }
    });

    it('answers unknown records without naming models', function () {
        $response = $this->withToken(tokenFor(apiUser(), ['core:read']))->getJson('/api/v1/files/'.Str::ulid())->assertNotFound();

        expect($response->json('message'))->toBe(__('core:errors.404.title'))
            ->and((string) $response->getContent())->not->toContain('App\\Models');
    });

    it('gives other sites no cross-origin access', function () {
        $response = $this->withToken(tokenFor(apiUser(), ['core:read']))
            ->getJson('/api/v1/me', ['Origin' => 'https://evil.example'])
            ->assertOk();

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });

    it('never treats a wildcard as a scope', function () {
        $user = apiUser();
        $secret = $user->createToken('wild', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($secret)->getJson('/api/v1/me')->assertForbidden();
    });
});

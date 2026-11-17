<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Audit\AuditLog;
use App\Audit\ChainKeys;
use App\Audit\ChainVerifier;
use App\Audit\VerificationResult;
use App\Authorization\RoleManager;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Tenancy\CrossTenantWrite;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

/*
 * S-0907: tamper-evident, hash-chained audit log.
 */

function lastEntry(?string $action = null): ?AuditEntry
{
    return AuditEntry::query()->when($action, fn ($q) => $q->where('action', $action))->orderByDesc('seq')->first();
}

function fillChain(int $count): void
{
    $audit = app(AuditLog::class);
    for ($i = 0; $i < $count; $i++) {
        $audit->record('test.event', ['things', (string) $i], ['n' => $i]);
    }
}

describe('events are logged', function () {
    it('records a sign-in with tenant, actor, subject, UTC time and IP', function () {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'], ['REMOTE_ADDR' => '203.0.113.7']);

        $entry = lastEntry('auth.login');
        expect($entry)->not->toBeNull()
            ->and($entry?->tenant_id)->toBe($this->tenant->id)
            ->and($entry?->actor_type)->toBe('user')
            ->and($entry?->actor_id)->toBe($user->id)
            ->and($entry?->subject_type)->toBe('users')
            ->and($entry?->subject_id)->toBe($user->id)
            ->and($entry?->ip_address)->toBe('203.0.113.7')
            ->and($entry?->occurred_at)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{1,6}$/');
    });

    it('records a failed sign-in without the submitted credentials', function () {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-secret']);

        $entry = lastEntry('auth.login_failed');
        $row = json_encode(DB::table('audit_log')->where('id', $entry?->id)->first());

        expect($entry?->actor_type)->toBe('guest')
            ->and($entry?->subject_id)->toBe($user->id)
            ->and($row)->not->toContain('wrong-secret')->not->toContain($user->email);
    });

    it('records role changes with the acting admin', function () {
        $admin = User::factory()->withTwoFactor()->create();
        $ana = User::factory()->create();

        $this->actingAs($admin);
        app(RoleManager::class)->assign($ana, Role::query()->where('key', 'employee')->firstOrFail());

        $entry = lastEntry('roles.assigned');
        expect($entry?->actor_id)->toBe($admin->id)
            ->and($entry?->subject_id)->toBe($ana->id)
            ->and($entry?->contextData())->toHaveKey('role_id');
    });

    it('records a password change and profile changes by field name only', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'a-new-Long-passphrase-42',
            'password_confirmation' => 'a-new-Long-passphrase-42',
        ]);
        $this->actingAs($user)->patch('/settings/profile', ['name' => 'Ana Test', 'email' => $user->email]);

        expect(lastEntry('auth.password_changed')?->subject_id)->toBe($user->id)
            ->and(lastEntry('users.profile_updated')?->contextData())->toBe(['fields' => ['name']])
            ->and(DB::table('audit_log')->where('context', 'like', '%Ana Test%')->exists())->toBeFalse();
    });

    it('records refused cross-tenant writes', function () {
        $foreign = Tenant::factory()->create();
        $row = new TenantModule(['module_key' => 'crm']);
        $row->setAttribute('tenant_id', $foreign->id);

        try {
            $row->save();
        } catch (CrossTenantWrite $e) {
            report($e);
        }

        expect(lastEntry('security.cross_tenant_write'))->not->toBeNull();
    });

    it('numbers entries without gaps, separately per tenant', function () {
        fillChain(3);
        $other = Tenant::factory()->create();
        app(TenantContext::class)->run($other, fn () => fillChain(2));

        $seqs = AuditEntry::query()->orderBy('seq')->pluck('seq')->all();
        $otherSeqs = DB::table('audit_log')->where('tenant_id', $other->id)->orderBy('seq')->pluck('seq')->map(fn ($s) => (int) $s)->all();

        expect($seqs)->toBe(range(1, count($seqs)))
            ->and($otherSeqs)->toBe(range(1, count($otherSeqs)));
    });
});

describe('hash chain', function () {
    it('finds a changed entry among 1,000 and reports it on the command line', function () {
        fillChain(1000);
        $target = AuditEntry::query()->where('action', 'test.event')->orderBy('seq')->skip(499)->firstOrFail();

        DB::table('audit_log')->where('id', $target->id)->update(['context' => '{"n":9999}']);

        $this->artisan('projektrack:audit:verify', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain("BROKEN at entry {$target->seq} (changed)")
            ->assertExitCode(1);

        expect(DB::table('audit_chain_heads')->where('tenant_id', $this->tenant->id)->value('broken_at_seq'))->toEqual($target->seq);
    });

    it('reports an intact chain', function () {
        fillChain(20);

        $this->artisan('projektrack:audit:verify', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('intact')
            ->assertExitCode(0);
    });

    it('detects a removed entry, a forged hash and a cut-off tail', function (string $tamper, string $problem) {
        fillChain(10);
        $chain = fn () => DB::table('audit_log')->where('tenant_id', $this->tenant->id);
        $last = (int) $chain()->max('seq');

        match ($tamper) {
            'remove' => $chain()->where('seq', 5)->delete(),
            'tail' => $chain()->where('seq', '>', $last - 2)->delete(),
            'forge' => (function () use ($chain): void {
                $row = (array) $chain()->where('seq', 5)->first();
                $row['action'] = 'test.other';
                // A plain hash, as someone without the chain key would compute it.
                $chain()->where('id', $row['id'])->update([
                    'action' => 'test.other',
                    'hash' => hash('sha256', $row['prev_hash'].AuditLog::canonical($row)),
                ]);
            })(),
        };

        $result = app(ChainVerifier::class)->verify($this->tenant->id);

        expect($result->intact)->toBeFalse()->and($result->problem)->toBe($problem);
    })->with([
        'removed entry' => ['remove', VerificationResult::GAP],
        'hash recomputed without the key' => ['forge', VerificationResult::CHANGED],
        'cut-off tail' => ['tail', VerificationResult::HEAD],
    ]);

    it('still verifies entries written before an application key rotation', function () {
        fillChain(5);
        $old = config('app.key');

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => [$old]]);
        app()->forgetInstance(ChainKeys::class);
        app()->forgetInstance(ChainVerifier::class);
        app()->forgetScopedInstances();
        fillChain(5);

        expect(app(ChainVerifier::class)->verify($this->tenant->id)->intact)->toBeTrue()
            ->and(AuditEntry::query()->distinct()->pluck('key_id')->count())->toBe(2);
    });

    it('shows the integrity warning to auditors only', function () {
        fillChain(3);
        DB::table('audit_log')->where('tenant_id', $this->tenant->id)->where('seq', 2)->update(['action' => 'x']);
        app(ChainVerifier::class)->verify($this->tenant->id);

        $admin = User::factory()->withTwoFactor()->create();
        app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());
        $employee = User::factory()->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('auditWarning', __('audit.integrity_warning', ['seq' => 2])));
        $this->actingAs($employee)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('auditWarning', null));
    });
});

describe('append-only', function () {
    it('offers no way to change, delete or hand-write an entry', function (string $operation) {
        fillChain(1);
        $entry = lastEntry();

        match ($operation) {
            'update' => $entry?->update(['action' => 'changed']),
            'delete' => $entry?->delete(),
            'create' => AuditEntry::query()->create(['action' => 'forged']),
        };
    })->with(['update', 'delete', 'create'])->throws(LogicException::class);

    it('keeps writes to the audit tables inside the audit writer', function () {
        $offenders = [];
        foreach ((new Finder)->files()->in(base_path('app'))->name('*.php') as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            if (str_starts_with($path, 'Audit/')) {
                continue;
            }

            if (preg_match("/table\\(\\s*'audit_(log|chain_heads)'\\s*\\)\\s*->\\s*(?!where\\([^;]*->\\s*value\\()/", $file->getContents()) === 1) {
                $offenders[] = $path;
            }
        }

        expect($offenders)->toBe([]);
    });
});

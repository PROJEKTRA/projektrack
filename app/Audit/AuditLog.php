<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Audit;

use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only writer of the audit log (S-0907).
 *
 * Appending locks the tenant's chain head, takes the next gapless sequence
 * number, hashes the entry together with the previous hash and inserts it in
 * the same transaction, so the entry commits atomically with the change it
 * describes. There is no code path that updates or deletes an entry.
 *
 * Context holds identifiers and field names, never personal content or
 * secrets (data minimization).
 */
final class AuditLog
{
    public const string GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private ?string $correlationId = null;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ChainKeys $keys,
    ) {}

    /**
     * @param  Model|array{0: string, 1: string|null}|null  $subject  a model, or [type, id]
     * @param  array<string, mixed>  $context
     * @param  string|null  $tenantId  defaults to the current tenant
     */
    public function record(
        string $action,
        Model|array|null $subject = null,
        array $context = [],
        ?User $actor = null,
        ?string $tenantId = null,
    ): void {
        $tenantId ??= $this->tenant->id();
        $request = app()->bound('request') ? app('request') : null;
        $request = $request instanceof Request ? $request : null;
        $actor ??= $this->currentUser($request);

        [$subjectType, $subjectId] = match (true) {
            $subject instanceof Model => [$subject->getTable(), self::scalarKey($subject->getKey())],
            is_array($subject) => [$subject[0], $subject[1]],
            default => [null, null],
        };

        $fields = [
            'tenant_id' => $tenantId,
            'occurred_at' => self::timestamp(CarbonImmutable::now('UTC')),
            'actor_type' => $actor instanceof User ? 'user' : ($request?->hasSession() ? 'guest' : 'system'),
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'context' => CanonicalJson::encode($context),
            'ip_address' => $request?->hasSession() ? $request->ip() : null,
            'correlation_id' => $this->correlationId ??= (string) Str::ulid(),
            'key_id' => $this->keys->currentId(),
        ];

        DB::transaction(function () use ($fields, $tenantId): void {
            DB::table('audit_chain_heads')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'last_seq' => 0,
                'last_hash' => self::GENESIS,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $head = DB::table('audit_chain_heads')->where('tenant_id', $tenantId)->lockForUpdate()->first(['last_seq', 'last_hash']);

            /** @var object{last_seq: int|string, last_hash: string} $head */
            $entry = $fields + [
                'seq' => (int) $head->last_seq + 1,
                'prev_hash' => $head->last_hash,
            ];
            $entry['hash'] = $this->keys->hash($entry['key_id'], $entry['prev_hash'], self::canonical($entry));

            DB::table('audit_log')->insert($entry);

            DB::table('audit_chain_heads')->where('tenant_id', $tenantId)->update([
                'last_seq' => $entry['seq'],
                'last_hash' => $entry['hash'],
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * The hashed form of an entry: every field except id and hash, with the
     * timestamp and context normalized so all engines give the same bytes.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function canonical(array $entry): string
    {
        $context = is_string($entry['context'] ?? null) ? json_decode($entry['context'], true, flags: JSON_THROW_ON_ERROR) : [];
        $occurred = $entry['occurred_at'] ?? null;

        return CanonicalJson::encode([
            'tenant_id' => $entry['tenant_id'] ?? null,
            'seq' => (int) (is_numeric($entry['seq'] ?? null) ? $entry['seq'] : 0),
            'occurred_at' => is_string($occurred) ? self::timestamp(CarbonImmutable::parse($occurred, 'UTC')) : null,
            'actor_type' => $entry['actor_type'] ?? null,
            'actor_id' => $entry['actor_id'] ?? null,
            'action' => $entry['action'] ?? null,
            'subject_type' => $entry['subject_type'] ?? null,
            'subject_id' => $entry['subject_id'] ?? null,
            'context' => $context,
            'ip_address' => $entry['ip_address'] ?? null,
            'correlation_id' => $entry['correlation_id'] ?? null,
            'key_id' => $entry['key_id'] ?? null,
            'prev_hash' => $entry['prev_hash'] ?? null,
        ]);
    }

    /** Stored form: UTC with microseconds, readable by every engine. */
    private static function timestamp(DateTimeInterface $time): string
    {
        return $time->format('Y-m-d H:i:s.u');
    }

    private static function scalarKey(mixed $key): ?string
    {
        return is_scalar($key) ? (string) $key : null;
    }

    private function currentUser(?Request $request): ?User
    {
        $user = $request?->hasSession() || Auth::hasUser() ? Auth::user() : null;

        return $user instanceof User ? $user : null;
    }
}

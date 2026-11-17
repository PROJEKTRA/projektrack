<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Recomputes a tenant's audit chain and finds the first broken entry: a
 * changed field, a removed or reordered entry, a forged hash or a cut-off tail
 * (compared with the chain head). The result is kept on the chain head for
 * the integrity warning in the admin UI.
 */
final class ChainVerifier
{
    private const int BATCH = 500;

    public function __construct(private readonly ChainKeys $keys) {}

    public function verify(string $tenantId): VerificationResult
    {
        $expectedSeq = 1;
        $prevHash = AuditLog::GENESIS;
        $broken = null;

        $query = DB::table('audit_log')->where('tenant_id', $tenantId)->orderBy('seq');

        foreach ($query->lazy(self::BATCH) as $row) {
            /** @var array<string, mixed> $entry */
            $entry = (array) $row;
            $seq = is_numeric($entry['seq'] ?? null) ? (int) $entry['seq'] : -1;
            $keyId = is_string($entry['key_id'] ?? null) ? $entry['key_id'] : '';
            $hash = is_string($entry['hash'] ?? null) ? $entry['hash'] : '';

            $problem = match (true) {
                $seq !== $expectedSeq => VerificationResult::GAP,
                ($entry['prev_hash'] ?? null) !== $prevHash => VerificationResult::LINK,
                ! $this->keys->has($keyId) => VerificationResult::UNKNOWN_KEY,
                ! hash_equals($this->keys->hash($keyId, $prevHash, AuditLog::canonical($entry)), $hash) => VerificationResult::CHANGED,
                default => null,
            };

            if ($problem !== null) {
                $broken = new VerificationResult(false, $expectedSeq - 1, $expectedSeq, $problem);
                break;
            }

            $prevHash = $hash;
            $expectedSeq++;
        }

        $checked = $expectedSeq - 1;
        $result = $broken ?? $this->compareWithHead($tenantId, $checked, $prevHash);

        DB::table('audit_chain_heads')->where('tenant_id', $tenantId)->update([
            'verified_at' => now(),
            'verified_seq' => $result->checked,
            'broken_at_seq' => $result->brokenAtSeq,
            'updated_at' => now(),
        ]);

        return $result;
    }

    /**
     * @return list<string> tenant ids that have an audit chain
     */
    public function tenants(): array
    {
        $ids = [];
        foreach (DB::table('audit_chain_heads')->orderBy('tenant_id')->pluck('tenant_id') as $id) {
            if (is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function compareWithHead(string $tenantId, int $checked, string $lastHash): VerificationResult
    {
        $head = DB::table('audit_chain_heads')->where('tenant_id', $tenantId)->first(['last_seq', 'last_hash']);

        if ($head === null) {
            return $checked === 0
                ? new VerificationResult(true, 0, null, null)
                : new VerificationResult(false, $checked, $checked, VerificationResult::HEAD);
        }

        /** @var object{last_seq: int|string, last_hash: string} $head */
        if ((int) $head->last_seq !== $checked || $head->last_hash !== $lastHash) {
            return new VerificationResult(false, $checked, min((int) $head->last_seq, $checked + 1), VerificationResult::HEAD);
        }

        return new VerificationResult(true, $checked, null, null);
    }
}

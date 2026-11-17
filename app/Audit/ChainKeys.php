<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Audit;

use RuntimeException;

/**
 * Keys of the audit hash chain (security baseline 8.2). They are derived from
 * the application key, which lives in the environment and not in the
 * database, so someone with write access to the database alone cannot compute
 * valid hashes for a changed entry.
 *
 * Every entry records the id of the key it was hashed with; after an app key
 * rotation (APP_PREVIOUS_KEYS) old entries still verify.
 */
final class ChainKeys
{
    private const string CONTEXT = 'projektrack.audit.chain.v1';

    /** @var array<string, string> key id => raw key */
    private array $keys = [];

    private string $currentId;

    /**
     * @param  list<string>  $appKeys  current key first, then previous keys
     */
    public function __construct(array $appKeys)
    {
        $first = null;
        foreach ($appKeys as $appKey) {
            $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;

            if (! is_string($raw) || $raw === '') {
                continue;
            }

            $derived = hash_hmac('sha256', self::CONTEXT, $raw, true);
            $id = substr(hash('sha256', $derived), 0, 16);
            $this->keys[$id] = $derived;
            $first ??= $id;
        }

        if ($first === null) {
            throw new RuntimeException('The audit log needs an application key (APP_KEY).');
        }

        $this->currentId = $first;
    }

    public static function fromConfig(): self
    {
        $current = config('app.key');
        $previous = config('app.previous_keys', []);

        $keys = is_string($current) ? [$current] : [];
        foreach (is_array($previous) ? $previous : [] as $key) {
            if (is_string($key)) {
                $keys[] = $key;
            }
        }

        return new self($keys);
    }

    public function currentId(): string
    {
        return $this->currentId;
    }

    public function has(string $id): bool
    {
        return isset($this->keys[$id]);
    }

    public function hash(string $keyId, string $prevHash, string $canonical): string
    {
        $key = $this->keys[$keyId] ?? throw new RuntimeException("Unknown audit chain key [{$keyId}].");

        return hash_hmac('sha256', $prevHash.$canonical, $key);
    }
}

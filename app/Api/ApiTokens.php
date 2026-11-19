<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Api;

use App\Audit\AuditLog;
use App\Models\ApiToken;
use App\Models\User;

/**
 * Personal access tokens (S-0913, security baseline section 2): scoped,
 * expiring (default 90 days, at most a year), shown once, stored hashed,
 * audited without the secret.
 */
final class ApiTokens
{
    public const int DEFAULT_DAYS = 90;

    public const int MAX_DAYS = 365;

    public function __construct(private readonly AuditLog $audit) {}

    /**
     * @param  list<string>  $scopes  validated against ApiScopes
     * @return array{token: ApiToken, secret: string} the secret is never available again
     */
    public function create(User $user, string $name, array $scopes, int $days = self::DEFAULT_DAYS): array
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $new = $user->createToken($name, array_values(array_unique($scopes)), now()->addDays($days));

        /** @var ApiToken $token */
        $token = $new->accessToken;
        $this->audit->record('api_tokens.created', $token, ['scopes' => $token->abilities, 'days' => $days], $user);

        return ['token' => $token, 'secret' => $new->plainTextToken];
    }

    /** @return list<ApiToken> the user's tokens, newest first */
    public function of(User $user): array
    {
        return array_values(ApiToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->latest('created_at')
            ->get()
            ->all());
    }

    /**
     * Revoke every token of the user (S-0913): after a password reset or
     * change, signing out other sessions, or a two-factor reset or removal, a
     * token minted by someone who had the account must stop working too.
     */
    public function revokeAll(User $user, string $reason): int
    {
        $deleted = ApiToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();
        $count = is_int($deleted) ? $deleted : 0;

        if ($count > 0) {
            $this->audit->record('api_tokens.revoked_all', $user, ['count' => $count, 'reason' => $reason]);
        }

        return $count;
    }

    public function revoke(User $user, string $id): bool
    {
        $token = ApiToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->find($id);

        if ($token === null) {
            return false;
        }

        $this->audit->record('api_tokens.revoked', $token, [], $user);
        $token->delete();

        return true;
    }
}

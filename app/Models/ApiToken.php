<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A personal access token for the REST API (S-0913). Only the SHA-256 of the
 * secret is stored; the tenant scope means a token is not even found on
 * another tenant's host.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tokenable_type
 * @property string $tokenable_id
 * @property string $name
 * @property string $token
 * @property list<string> $abilities
 * @property CarbonInterface|null $last_used_at
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $created_at
 */
class ApiToken extends PersonalAccessToken
{
    use BelongsToTenant, HasUlids;

    protected $table = 'api_tokens';

    /** @var list<string> */
    protected $hidden = ['token'];

    /**
     * Only scopes named on the token count; a wildcard grants nothing.
     *
     * @param  string  $ability
     */
    public function can($ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }

    /**
     * @param  string  $ability
     */
    public function cant($ability): bool
    {
        return ! $this->can($ability);
    }
}

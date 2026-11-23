<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A link that gives a person outside the instance access to exactly one
 * object (S-0987). Create, resolve and revoke it only through
 * App\SharedLinks\SharedLinks; only the SHA-256 of the token is stored.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $created_by
 * @property string $target_type
 * @property string $target_id
 * @property list<string> $actions
 * @property string $token
 * @property string|null $password
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $revoked_at
 * @property string|null $revoked_by
 * @property int $failed_attempts
 * @property CarbonInterface|null $locked_until
 * @property CarbonInterface|null $last_accessed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['created_by', 'target_type', 'target_id', 'actions', 'token', 'password', 'expires_at'])]
class SharedLink extends Model
{
    use BelongsToTenant, HasUlids;

    /** @var list<string> */
    protected $hidden = ['token', 'password'];

    public function allows(string $action): bool
    {
        return in_array($action, $this->actions, true);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_accessed_at' => 'datetime',
            'failed_attempts' => 'integer',
        ];
    }
}

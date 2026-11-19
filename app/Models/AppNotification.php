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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An in-app notification (S-0912). `data` holds string identifiers and
 * arguments, never finished text, so it is shown in the reader's language.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $type
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array{message: string, args?: array<string, scalar>, url?: string|null} $data
 * @property CarbonInterface|null $read_at
 * @property CarbonInterface|null $emailed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['user_id', 'type', 'subject_type', 'subject_id', 'data', 'read_at', 'emailed_at'])]
class AppNotification extends Model
{
    use BelongsToTenant, HasUlids;

    protected $table = 'notifications';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A user's choice for one notification type and channel (S-0912). Stored
 * only where it differs from the default (e-mail on).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $type
 * @property string $channel
 * @property string $frequency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'type', 'channel', 'frequency'])]
class NotificationPreference extends Model
{
    use BelongsToTenant, HasUlids;
}

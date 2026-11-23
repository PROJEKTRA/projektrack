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
 * One use of a shared link (S-0987): what was asked, how it ended, a
 * shortened address and the browser family. Never the token.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $shared_link_id
 * @property string $action
 * @property string $outcome
 * @property string|null $ip_prefix
 * @property string $browser
 * @property CarbonInterface $occurred_at
 */
#[Fillable(['shared_link_id', 'action', 'outcome', 'ip_prefix', 'browser', 'occurred_at'])]
class SharedLinkAccess extends Model
{
    use BelongsToTenant, HasUlids;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}

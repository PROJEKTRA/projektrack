<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

namespace Modules\Licensing\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * History of an order; never changed.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $license_order_id
 * @property string $event
 * @property string|null $actor_id
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['license_order_id', 'event', 'actor_id'])]
class LicenseOrderEvent extends Model
{
    use BelongsToTenant, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('History does not change.'));
        static::deleting(fn () => throw new LogicException('History is not deleted.'));
    }
}

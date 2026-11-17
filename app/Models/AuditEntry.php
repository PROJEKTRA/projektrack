<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Audit\AuditLog;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Read model of one audit entry (S-0907). Entries are written only by
 * AuditLog and are never changed or deleted.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $seq
 * @property string $occurred_at
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string $context
 * @property string|null $ip_address
 * @property string|null $correlation_id
 * @property string $key_id
 * @property string $prev_hash
 * @property string $hash
 */
class AuditEntry extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'audit_log';

    /**
     * Nothing is guarded because nothing can be written: every create, update
     * and delete is refused with a clear error below.
     *
     * @var list<string>
     */
    protected $guarded = [];

    protected static function booted(): void
    {
        $refuse = function (): never {
            throw new LogicException('Audit entries are append-only and are written by '.AuditLog::class.' only.');
        };

        static::creating($refuse);
        static::updating($refuse);
        static::deleting($refuse);
    }

    /**
     * @return array<string, mixed>
     */
    public function contextData(): array
    {
        $data = json_decode($this->context, true);

        if (! is_array($data)) {
            return [];
        }

        $context = [];
        foreach ($data as $key => $value) {
            $context[(string) $key] = $value;
        }

        return $context;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['seq' => 'integer'];
    }
}

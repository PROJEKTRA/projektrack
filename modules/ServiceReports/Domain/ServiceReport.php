<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\ServiceReports\Domain;

use App\Authorization\ProjectScoped;
use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A generated service report. Its content never changes; a new one is generated instead.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $number
 * @property string $project_id
 * @property CarbonInterface $period_from
 * @property CarbonInterface $period_to
 * @property string $locale
 * @property bool $with_amounts
 * @property string $snapshot
 * @property string $content_hash
 * @property string $created_by
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['number', 'project_id', 'period_from', 'period_to', 'locale', 'with_amounts', 'snapshot', 'content_hash', 'created_by'])]
class ServiceReport extends Model implements ProjectScoped
{
    use BelongsToTenant, HasUlids;

    public function scopedProjectId(): string
    {
        return $this->project_id;
    }

    /** @return array<mixed> the content, checked against its hash */
    public function content(): array
    {
        if (! hash_equals($this->content_hash, hash('sha256', $this->snapshot))) {
            throw new LogicException("The content of {$this->number} was changed.");
        }
        $content = json_decode($this->snapshot, true, flags: JSON_THROW_ON_ERROR);

        return is_array($content) ? $content : [];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Service reports do not change.'));
        static::deleting(fn () => throw new LogicException('Service reports are not deleted.'));
    }

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'with_amounts' => 'boolean'];
    }
}

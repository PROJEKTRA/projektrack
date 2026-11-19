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
 * An uploaded file (S-0909). Read it only through App\Files\FileStore:
 * downloads go through signed, expiring links.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $disk
 * @property string $storage_key
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property string $scan_status
 * @property string $context
 * @property CarbonInterface|null $retain_until
 * @property string|null $uploaded_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['disk', 'storage_key', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'scan_status', 'context', 'retain_until', 'uploaded_by'])]
class StoredFile extends Model
{
    use BelongsToTenant, HasUlids;

    protected $table = 'files';

    /** Shown inline (with a sandbox policy) instead of downloaded. */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'retain_until' => 'date',
        ];
    }
}

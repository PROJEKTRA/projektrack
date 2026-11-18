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
 * A tenant's own text for one identifier in one language (ADR-0005 section 4).
 * The strings of a copied language are overrides of that language.
 *
 * @property string $id
 * @property string $scope
 * @property string $tenant_id
 * @property string $locale
 * @property string $identifier
 * @property string $value
 * @property string|null $source_hash
 * @property bool $needs_review
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['scope', 'locale', 'identifier', 'value', 'source_hash', 'needs_review', 'updated_by'])]
class StringOverride extends Model
{
    use BelongsToTenant, HasUlids;

    protected $attributes = ['scope' => 'tenant'];

    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
        ];
    }
}

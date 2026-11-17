<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Authorization\FieldAccess;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A role's override of a declared field's default access.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $role_id
 * @property string $entity_type
 * @property string $field
 * @property FieldAccess $access
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['role_id', 'entity_type', 'field', 'access'])]
class FieldPermission extends Model
{
    use BelongsToTenant, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['access' => FieldAccess::class];
    }
}

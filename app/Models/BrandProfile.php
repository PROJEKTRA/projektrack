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
 * A tenant's brand (S-0911). Read it through App\Branding\Brand, which fills
 * empty values from the instance brand.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $product_name
 * @property string|null $logo_file_id
 * @property string|null $primary_color
 * @property string|null $email_sender_name
 * @property bool $show_powered_by
 * @property string|null $updated_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
// show_powered_by is not fillable: removing the endorsement needs a license entitlement (ADR-0006).
#[Fillable(['product_name', 'logo_file_id', 'primary_color', 'email_sender_name', 'updated_by'])]
class BrandProfile extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['show_powered_by' => 'boolean'];
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Domain;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * What somebody works as, for example Technical Architect; not a permission role.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 */
#[Fillable(['name'])]
class JobRole extends Model
{
    use BelongsToTenant, HasUlids;
}

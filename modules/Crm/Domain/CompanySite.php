<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Domain;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A site of a company; its address is kept in addresses.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string $name
 */
#[Fillable(['company_id', 'name'])]
class CompanySite extends Model
{
    use BelongsToTenant, HasUlids;
}

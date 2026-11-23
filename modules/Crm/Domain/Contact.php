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
 * A person at a company.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $company_id
 * @property string|null $first_name
 * @property string $last_name
 * @property string|null $email_normalized
 * @property string|null $phone
 * @property string|null $position
 * @property bool $invoice_recipient
 */
#[Fillable(['company_id', 'first_name', 'last_name', 'email_normalized', 'phone', 'position', 'invoice_recipient'])]
class Contact extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['invoice_recipient' => 'boolean'];
    }
}

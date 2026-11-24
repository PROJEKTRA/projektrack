<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A user in a project with one project role.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $user_id
 * @property string $project_role_id
 * @property CarbonInterface|null $joined_on
 * @property CarbonInterface|null $left_on
 */
#[Fillable(['project_id', 'user_id', 'project_role_id', 'joined_on', 'left_on'])]
class ProjectMember extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['joined_on' => 'date', 'left_on' => 'date'];
    }
}

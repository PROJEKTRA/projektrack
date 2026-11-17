<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Support\Lists;
use App\Tenancy\BelongsToTenant;
use Database\Factories\ProjectRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A role a user holds inside one project (Project Lead, Member, Viewer). The
 * projects module links users to projects with these roles (project_members).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $key
 * @property string|null $name_string
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name_string', 'is_system'])]
class ProjectRole extends Model
{
    /** @use HasFactory<ProjectRoleFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return Lists::strings(DB::table('project_role_permissions')
            ->where('tenant_id', $this->tenant_id)
            ->where('project_role_id', $this->id)
            ->orderBy('permission_key')
            ->pluck('permission_key'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}

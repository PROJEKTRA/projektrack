<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Models;

use App\Authorization\UserType;
use App\Support\Lists;
use App\Tenancy\BelongsToTenant;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A global role of one tenant (S-0906). System roles come from the role
 * templates; tenants add their own. Grants change only through RoleManager.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $key
 * @property string|null $name_string
 * @property string|null $template_key
 * @property bool $is_system
 * @property bool $is_portal
 * @property bool $requires_two_factor
 * @property UserType $licensed_user_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name_string', 'template_key', 'is_system', 'is_portal', 'requires_two_factor'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const string ADMIN = 'admin';

    /** @var array<string, mixed> */
    protected $attributes = ['licensed_user_type' => 'light'];

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            if ($role->is_portal) {
                $role->licensed_user_type = UserType::Portal;
            }
        });
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return Lists::strings(DB::table('role_permissions')
            ->where('tenant_id', $this->tenant_id)
            ->where('role_id', $this->id)
            ->orderBy('permission_key')
            ->pluck('permission_key'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_portal' => 'boolean',
            'requires_two_factor' => 'boolean',
            'licensed_user_type' => UserType::class,
        ];
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

/**
 * The role templates every new tenant starts with (S-0906). Modules add their
 * suggested grants when a tenant first enables them (role_defaults).
 */
final class RoleTemplates
{
    /**
     * key => [core permissions, requires two-factor, portal role]
     *
     * @var array<string, array{0: list<string>, 1: bool, 2: bool}>
     */
    public const array ROLES = [
        'admin' => [['core.admin', 'core.users.manage', 'core.roles.manage', 'core.settings.manage', 'core.modules.manage',
            'core.strings.customize', 'core.audit.view', 'core.audit.export', 'core.files.upload', 'core.api_tokens.manage',
            'core.shared_links.manage', 'core.branding.manage', 'core.license.manage', 'core.data_requests.manage'], true, false],
        'controller' => [['core.audit.view', 'core.audit.export', 'core.files.upload'], true, false],
        'project_lead' => [['core.files.upload', 'core.shared_links.manage'], false, false],
        'employee' => [['core.files.upload'], false, false],
        'external' => [['core.files.upload'], false, false],
        'customer' => [[], false, true],
    ];

    /** @var list<string> */
    public const array PROJECT_ROLES = ['project_lead', 'project_member', 'project_viewer'];

    public function __construct(
        private readonly RoleManager $roles,
        private readonly TenantContext $context,
    ) {}

    public function install(Tenant $tenant): void
    {
        $this->context->run($tenant, function (): void {
            foreach (self::ROLES as $key => [$permissions, $twoFactor, $portal]) {
                $role = Role::query()->firstOrCreate(['key' => $key], [
                    'name_string' => "core:roles.{$key}",
                    'template_key' => $key,
                    'is_system' => true,
                    'is_portal' => $portal,
                    'requires_two_factor' => $twoFactor,
                ]);

                if ($permissions !== []) {
                    $this->roles->grant($role, $permissions);
                }
            }

            foreach (self::PROJECT_ROLES as $key) {
                ProjectRole::query()->firstOrCreate(['key' => $key], [
                    'name_string' => "core:project_roles.{$key}",
                    'is_system' => true,
                ]);
            }
        });
    }
}

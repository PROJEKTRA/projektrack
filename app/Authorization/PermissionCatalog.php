<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

use App\Modules\ModuleManifest;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * Every permission and sensitive field known to this installation: the core's
 * own plus those declared by valid modules (modules.md, section 2).
 *
 * A permission that is not in this catalog does not exist: it cannot be granted
 * and an old grant of it has no effect.
 */
final class PermissionCatalog
{
    /**
     * Core permissions (modules.md, catalog) with their risk level.
     */
    public const array CORE = [
        'core.admin' => 'high',
        'core.users.manage' => 'high',
        'core.roles.manage' => 'high',
        'core.settings.manage' => 'high',
        'core.modules.manage' => 'high',
        'core.strings.customize' => 'normal',
        'core.audit.view' => 'normal',
        'core.audit.export' => 'high',
        'core.files.upload' => 'normal',
        'core.api_tokens.manage' => 'high',
        'core.shared_links.manage' => 'normal',
        'core.branding.manage' => 'normal',
        'core.license.manage' => 'high',
        'core.data_requests.manage' => 'high',
    ];

    /** @var array<string, string> permission key => module key */
    private array $modules = [];

    /** @var array<string, FieldDeclaration> "entity.field" => declaration */
    private array $fields = [];

    public function __construct(private readonly ModuleRegistry $registry, LoggerInterface $logger)
    {
        foreach (array_keys(self::CORE) as $key) {
            $this->modules[$key] = ModuleManifest::CORE;
        }

        foreach ($registry->all() as $module) {
            foreach ($module->permissions as $key) {
                $this->modules[$key] ??= $module->key;
            }

            foreach ($module->fieldPermissions as $declaration) {
                $id = "{$declaration->entity}.{$declaration->field}";

                if (isset($this->fields[$id])) {
                    $logger->warning('Field {field} is declared by more than one module; {module} is ignored.', [
                        'field' => $id,
                        'module' => $module->key,
                    ]);

                    continue;
                }

                $this->fields[$id] = $declaration->withModule($module->key);
            }
        }
    }

    public function has(string $permission): bool
    {
        return isset($this->modules[$permission]);
    }

    public function moduleOf(string $permission): ?string
    {
        return $this->modules[$permission] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->modules);
    }

    public function field(string $entity, string $field): ?FieldDeclaration
    {
        return $this->fields["{$entity}.{$field}"] ?? null;
    }

    /**
     * @return list<FieldDeclaration>
     */
    public function fieldsOf(string $entity): array
    {
        return array_values(array_filter($this->fields, fn (FieldDeclaration $d): bool => $d->entity === $entity));
    }

    /**
     * @return array<string, list<string>>
     */
    public function roleDefaults(string $module): array
    {
        return $this->registry->has($module) ? $this->registry->get($module)->roleDefaults : [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function projectRoleDefaults(string $module): array
    {
        return $this->registry->has($module) ? $this->registry->get($module)->projectRoleDefaults : [];
    }

    /**
     * Make sure the given permissions exist in the instance-wide table, which
     * grants reference by foreign key.
     *
     * @param  list<string>  $permissions
     *
     * @throws UnknownPermission
     */
    public function persist(array $permissions): void
    {
        $rows = [];
        foreach ($permissions as $key) {
            $module = $this->moduleOf($key) ?? throw new UnknownPermission($key);
            $rows[] = [
                'key' => $key,
                'module_key' => $module,
                'risk_level' => self::CORE[$key] ?? 'normal',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('permissions')->upsert($rows, ['key'], ['module_key', 'risk_level', 'updated_at']);
        }
    }

    /**
     * Write the whole catalog to the permissions table (install and update).
     */
    public function sync(): int
    {
        $this->persist($this->keys());

        return count($this->modules);
    }
}

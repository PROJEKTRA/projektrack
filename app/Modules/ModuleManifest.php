<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

use App\Authorization\FieldAccess;
use App\Authorization\FieldDeclaration;
use App\Authorization\FieldRisk;

/**
 * A validated module.json (docs/architecture/modules.md, section 2).
 *
 * Only the fields the registry needs in 0.9 are modelled; the full manifest is
 * kept in $raw so later stories (navigation, field permissions, AI use cases)
 * read it without a schema change here.
 */
final readonly class ModuleManifest
{
    public const string CORE = 'core';

    private const string KEY_PATTERN = '/^[a-z][a-z0-9_]{1,40}$/';

    private const string STRING_ID_PATTERN = '/^[a-z][a-z0-9_]*:[a-z0-9_.]+$/';

    private const string SEMVER_PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

    private const string PERMISSION_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    private const string NAME_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /** Namespace owned by the tax engine of the invoices module (ADR-0004). */
    private const string TAX_NAMESPACE = 'tax.';

    /**
     * @param  list<string>  $requires  hard dependencies, without "core"
     * @param  list<string>  $optional
     * @param  list<string>  $conflicts
     * @param  list<string>  $permissions
     * @param  list<string>  $featureFlags
     * @param  list<FieldDeclaration>  $fieldPermissions
     * @param  array<string, list<string>>  $roleDefaults  role template key => permissions granted on first enable
     * @param  array<string, list<string>>  $projectRoleDefaults  project role template key => permissions
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public string $key,
        public string $nameString,
        public string $version,
        public string $coreConstraint,
        public Edition $edition,
        public array $requires,
        public array $optional,
        public array $conflicts,
        public array $permissions,
        public array $featureFlags,
        public array $fieldPermissions,
        public array $roleDefaults,
        public array $projectRoleDefaults,
        public string $path,
        public array $raw,
    ) {}

    /**
     * Read and validate a module.json file.
     *
     * @throws InvalidModuleManifest
     */
    public static function fromFile(string $file): self
    {
        $json = @file_get_contents($file);

        if ($json === false) {
            throw new InvalidModuleManifest($file, ['file cannot be read']);
        }

        $data = json_decode($json, true);

        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidModuleManifest($file, ['file is not a JSON object']);
        }

        /** @var array<string, mixed> $data */
        return self::fromArray($data, dirname($file));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidModuleManifest
     */
    public static function fromArray(array $data, string $path): self
    {
        $problems = [];

        $key = self::requiredString($data, 'key', self::KEY_PATTERN, $problems);
        $nameString = self::requiredString($data, 'name_string', self::STRING_ID_PATTERN, $problems);
        $version = self::requiredString($data, 'version', self::SEMVER_PATTERN, $problems);
        $core = self::requiredString($data, 'core', null, $problems);

        $editionValue = $data['edition'] ?? null;
        $edition = is_string($editionValue) ? Edition::tryFrom($editionValue) : null;
        if ($edition === null) {
            $problems[] = '"edition" must be one of '.implode(', ', array_column(Edition::cases(), 'value'));
        }

        if ($key === ModuleManifest::CORE) {
            $problems[] = '"key" must not be "core": the core is not a module';
        }

        $requires = self::keyList($data, 'requires', true, $problems);
        $optional = self::keyList($data, 'optional', false, $problems);
        $conflicts = self::keyList($data, 'conflicts', false, $problems);
        $permissions = self::stringList($data, 'permissions', self::PERMISSION_PATTERN, true, $problems);
        $flags = self::stringList($data, 'feature_flags', self::PERMISSION_PATTERN, false, $problems);

        if ($key !== '' && in_array($key, array_merge($requires, $optional, $conflicts), true)) {
            $problems[] = 'a module cannot depend on or conflict with itself';
        }

        foreach ($permissions as $permission) {
            $ownNamespace = $key !== '' && str_starts_with($permission, $key.'.');
            $taxNamespace = $key === 'invoices' && str_starts_with($permission, self::TAX_NAMESPACE);

            if (! $ownNamespace && ! $taxNamespace) {
                $problems[] = "permission \"{$permission}\" must start with the module key";
            }
        }

        $fields = self::fieldPermissions($data, $permissions, $problems);
        $roleDefaults = self::defaults($data, 'role_defaults', $permissions, $problems);
        $projectRoleDefaults = self::defaults($data, 'project_role_defaults', $permissions, $problems);

        if ($problems !== [] || $edition === null) {
            throw new InvalidModuleManifest($path, $problems);
        }

        return new self(
            key: $key,
            nameString: $nameString,
            version: $version,
            coreConstraint: $core,
            edition: $edition,
            requires: array_values(array_filter($requires, fn (string $k): bool => $k !== self::CORE)),
            optional: $optional,
            conflicts: $conflicts,
            permissions: $permissions,
            featureFlags: $flags,
            fieldPermissions: $fields,
            roleDefaults: $roleDefaults,
            projectRoleDefaults: $projectRoleDefaults,
            path: $path,
            raw: $data,
        );
    }

    /**
     * "field_permissions": sensitive fields of the module's entities. Financial
     * and security fields must default to hidden (deny by default).
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $permissions
     * @param  list<string>  $problems
     * @return list<FieldDeclaration>
     */
    private static function fieldPermissions(array $data, array $permissions, array &$problems): array
    {
        $value = $data['field_permissions'] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            $problems[] = '"field_permissions" must be a list';

            return [];
        }

        $fields = [];
        foreach ($value as $i => $item) {
            $entity = is_array($item) ? ($item['entity'] ?? null) : null;
            $field = is_array($item) ? ($item['field'] ?? null) : null;
            $default = is_array($item) && is_string($item['default'] ?? null) ? FieldAccess::tryFrom($item['default']) : null;
            $riskValue = is_array($item) ? ($item['risk'] ?? 'none') : null;
            $risk = is_string($riskValue) ? FieldRisk::tryFrom($riskValue) : null;
            $permission = is_array($item) ? ($item['permission'] ?? null) : null;

            if (! is_string($entity) || preg_match(self::NAME_PATTERN, $entity) !== 1
                || ! is_string($field) || preg_match(self::NAME_PATTERN, $field) !== 1
                || $default === null || $risk === null
                || ($permission !== null && (! is_string($permission) || ! in_array($permission, $permissions, true)))) {
                $problems[] = "\"field_permissions\" entry {$i} is invalid";

                continue;
            }

            if ($risk->mustDefaultToHidden() && $default !== FieldAccess::Hidden) {
                $problems[] = "\"field_permissions\" entry {$i} holds {$risk->value} data and must default to hidden";

                continue;
            }

            $fields[] = new FieldDeclaration($entity, $field, $default, $risk, $permission);
        }

        return $fields;
    }

    /**
     * "role_defaults" / "project_role_defaults": what the module suggests for the
     * role templates. Applied once, when a tenant first enables the module.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $permissions
     * @param  list<string>  $problems
     * @return array<string, list<string>>
     */
    private static function defaults(array $data, string $name, array $permissions, array &$problems): array
    {
        $value = $data[$name] ?? [];

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $problems[] = "\"{$name}\" must be an object";

            return [];
        }

        $defaults = [];
        foreach ($value as $role => $granted) {
            if (! is_string($role) || preg_match(self::NAME_PATTERN, $role) !== 1 || ! is_array($granted) || ! array_is_list($granted)) {
                $problems[] = "\"{$name}\" is invalid";

                return [];
            }

            $keys = [];
            foreach ($granted as $permission) {
                if (! is_string($permission) || ! in_array($permission, $permissions, true)) {
                    $problems[] = "\"{$name}\" grants a permission the module does not declare";

                    return [];
                }
                $keys[] = $permission;
            }

            $defaults[$role] = array_values(array_unique($keys));
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $problems
     */
    private static function requiredString(array $data, string $field, ?string $pattern, array &$problems): string
    {
        $value = $data[$field] ?? null;

        if (! is_string($value) || $value === '') {
            $problems[] = "\"{$field}\" is missing";

            return '';
        }

        if ($pattern !== null && preg_match($pattern, $value) !== 1) {
            $problems[] = "\"{$field}\" has an invalid format";

            return '';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $problems
     * @return list<string>
     */
    private static function keyList(array $data, string $field, bool $required, array &$problems): array
    {
        return self::stringList($data, $field, self::KEY_PATTERN, $required, $problems);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $problems
     * @return list<string>
     */
    private static function stringList(array $data, string $field, string $pattern, bool $required, array &$problems): array
    {
        if (! array_key_exists($field, $data)) {
            if ($required) {
                $problems[] = "\"{$field}\" is missing";
            }

            return [];
        }

        $value = $data[$field];

        if (! is_array($value) || ! array_is_list($value)) {
            $problems[] = "\"{$field}\" must be a list";

            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_string($item) || preg_match($pattern, $item) !== 1) {
                $problems[] = "\"{$field}\" contains an invalid entry";

                return [];
            }
            $items[] = $item;
        }

        if (count($items) !== count(array_unique($items))) {
            $problems[] = "\"{$field}\" contains duplicates";
        }

        return $items;
    }
}

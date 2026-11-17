<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

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

    /**
     * @param  list<string>  $requires  hard dependencies, without "core"
     * @param  list<string>  $optional
     * @param  list<string>  $conflicts
     * @param  list<string>  $permissions
     * @param  list<string>  $featureFlags
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
            path: $path,
            raw: $data,
        );
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

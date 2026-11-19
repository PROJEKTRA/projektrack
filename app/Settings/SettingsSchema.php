<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Settings;

use InvalidArgumentException;

/**
 * Reads a settings.schema.json into setting definitions (S-0912). A schema
 * that uses anything this reader does not support is refused, so a module
 * cannot declare a setting the form would show wrongly or not validate.
 */
final class SettingsSchema
{
    private const array TYPES = ['string', 'integer', 'number', 'boolean'];

    private const array KEYWORDS = [
        'type', 'default', 'enum', 'minimum', 'maximum', 'maxLength', 'pattern', 'description',
        'x-label', 'x-help', 'x-options', 'x-enum-labels', 'x-null-label', 'x-tenant-attribute',
    ];

    /** Lists the application provides for x-options. */
    public const array OPTIONS = ['languages', 'formats', 'timezones'];

    /** Tenant columns a core setting may keep its value in. */
    public const array TENANT_ATTRIBUTES = ['default_locale', 'timezone'];

    /** @return list<SettingDefinition> */
    public static function fromFile(string $component, string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            throw new InvalidArgumentException("{$file}: not valid JSON.");
        }

        return self::fromArray($component, $data, $file);
    }

    /**
     * @param  array<mixed>  $schema
     * @return list<SettingDefinition>
     */
    public static function fromArray(string $component, array $schema, string $source = 'schema'): array
    {
        if (($schema['type'] ?? null) !== 'object' || ! is_array($schema['properties'] ?? null)) {
            throw new InvalidArgumentException("{$source}: expected an object schema with properties.");
        }

        $definitions = [];

        foreach ($schema['properties'] as $name => $property) {
            if (! is_string($name) || preg_match('/^[a-z][a-z0-9_]{0,60}$/', $name) !== 1 || ! is_array($property)) {
                throw new InvalidArgumentException("{$source}: invalid setting name.");
            }

            $definitions[] = self::definition($component, $name, $property, "{$source}#{$name}");
        }

        return $definitions;
    }

    /**
     * @param  array<mixed>  $property
     */
    private static function definition(string $component, string $name, array $property, string $where): SettingDefinition
    {
        $unknown = [];
        foreach (array_keys($property) as $keyword) {
            if (! in_array($keyword, self::KEYWORDS, true)) {
                $unknown[] = (string) $keyword;
            }
        }
        if ($unknown !== []) {
            throw new InvalidArgumentException("{$where}: unsupported keyword ".implode(', ', $unknown).'.');
        }

        $types = array_values(array_filter((array) ($property['type'] ?? null), is_string(...)));
        $nullable = in_array('null', $types, true);
        $types = array_values(array_diff($types, ['null']));

        if (count($types) !== 1 || ! in_array($types[0], self::TYPES, true)) {
            throw new InvalidArgumentException("{$where}: type must be one of ".implode(', ', self::TYPES).', optionally with null.');
        }

        $label = $property['x-label'] ?? null;
        if (! is_string($label) || ! str_contains($label, ':')) {
            throw new InvalidArgumentException("{$where}: x-label must be a string identifier.");
        }

        $options = $property['x-options'] ?? null;
        if ($options !== null && ! in_array($options, self::OPTIONS, true)) {
            throw new InvalidArgumentException("{$where}: unknown x-options.");
        }

        $attribute = $property['x-tenant-attribute'] ?? null;
        if ($attribute !== null && ($component !== 'core' || ! in_array($attribute, self::TENANT_ATTRIBUTES, true))) {
            throw new InvalidArgumentException("{$where}: x-tenant-attribute is for core settings on known tenant columns only.");
        }

        $enum = isset($property['enum']) && is_array($property['enum']) ? array_values($property['enum']) : null;
        $enumLabels = isset($property['x-enum-labels']) && is_array($property['x-enum-labels']) ? array_values(array_map(fn (mixed $label): string => is_scalar($label) ? (string) $label : '', $property['x-enum-labels'])) : null;

        if ($enum !== null && $enumLabels !== null && count($enum) !== count($enumLabels)) {
            throw new InvalidArgumentException("{$where}: x-enum-labels needs one label per enum value.");
        }

        /** @var list<string|int|float|bool>|null $enum */
        return new SettingDefinition(
            component: $component,
            name: $name,
            type: $types[0],
            nullable: $nullable,
            default: $property['default'] ?? null,
            label: $label,
            help: is_string($property['x-help'] ?? null) ? $property['x-help'] : null,
            enum: $enum,
            enumLabels: $enumLabels,
            options: is_string($options) ? $options : null,
            nullLabel: is_string($property['x-null-label'] ?? null) ? $property['x-null-label'] : null,
            minimum: is_int($property['minimum'] ?? null) || is_float($property['minimum'] ?? null) ? $property['minimum'] : null,
            maximum: is_int($property['maximum'] ?? null) || is_float($property['maximum'] ?? null) ? $property['maximum'] : null,
            maxLength: is_int($property['maxLength'] ?? null) ? $property['maxLength'] : null,
            pattern: is_string($property['pattern'] ?? null) ? $property['pattern'] : null,
            tenantAttribute: is_string($attribute) ? $attribute : null,
        );
    }
}

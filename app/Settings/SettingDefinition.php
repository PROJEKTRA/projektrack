<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Settings;

/**
 * One setting from a settings.schema.json (S-0912). Supported JSON Schema
 * keywords: type (string, integer, number, boolean, optionally with "null"),
 * default, enum, minimum, maximum, maxLength, pattern; and the extensions
 * x-label, x-help, x-options, x-enum-labels, x-null-label,
 * x-tenant-attribute.
 */
final readonly class SettingDefinition
{
    /**
     * @param  list<string|int|float|bool>|null  $enum
     * @param  list<string>|null  $enumLabels
     */
    public function __construct(
        public string $component,
        public string $name,
        public string $type,
        public bool $nullable,
        public mixed $default,
        public string $label,
        public ?string $help,
        public ?array $enum = null,
        public ?array $enumLabels = null,
        public ?string $options = null,
        public ?string $nullLabel = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?string $tenantAttribute = null,
    ) {}

    /** Key as used in code: core.timezone, timesheets.week_start. */
    public function key(): string
    {
        return "{$this->component}.{$this->name}";
    }

    /**
     * Validation rules; $allowed holds the values of x-options.
     *
     * @param  list<string>|null  $allowed
     * @return list<mixed>
     */
    public function rules(?array $allowed): array
    {
        $rules = [$this->nullable ? 'nullable' : 'required'];

        $rules[] = match ($this->type) {
            'boolean' => 'boolean',
            'integer' => 'integer',
            'number' => 'numeric',
            default => 'string',
        };

        if ($this->minimum !== null) {
            $rules[] = 'min:'.$this->minimum;
        }
        if ($this->maximum !== null) {
            $rules[] = 'max:'.$this->maximum;
        }
        if ($this->maxLength !== null) {
            $rules[] = 'max:'.$this->maxLength;
        }
        if ($this->pattern !== null) {
            $rules[] = 'regex:'.self::regex($this->pattern);
        }

        $choices = $allowed ?? $this->enum;
        if ($choices !== null) {
            $rules[] = 'in:'.implode(',', array_map(fn (mixed $value): string => is_bool($value) ? ($value ? '1' : '0') : (string) $value, $choices));
        }

        return $rules;
    }

    /** The pattern as a PHP regular expression (# delimited). */
    public static function regex(string $pattern): string
    {
        return '#'.str_replace('#', '\#', $pattern).'#u';
    }

    /** Bring a validated input to the declared type. */
    public function cast(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $this->nullable ? null : $this->default;
        }

        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => is_numeric($value) ? (int) $value : $this->default,
            'number' => is_numeric($value) ? (float) $value : $this->default,
            default => is_scalar($value) ? (string) $value : $this->default,
        };
    }
}

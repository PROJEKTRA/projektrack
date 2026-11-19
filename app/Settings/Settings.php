<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Settings;

use App\Audit\AuditLog;
use App\I18n\Formats;
use App\I18n\TenantLanguages;
use App\Models\Setting;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Settings of the current tenant (S-0912): declared by the core
 * (resources/settings/settings.schema.json) and by every enabled module
 * (its settings_schema), stored only where they differ from the default.
 * Scoped per request.
 */
final class Settings
{
    /** @var array<string, list<SettingDefinition>>|null */
    private ?array $definitions = null;

    /** @var array<string, mixed>|null */
    private ?array $stored = null;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly TenantModules $modules,
        private readonly ModuleRegistry $registry,
        private readonly AuditLog $audit,
    ) {}

    /**
     * Definitions by component: core first, then enabled modules.
     *
     * @return array<string, list<SettingDefinition>>
     */
    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = ['core' => SettingsSchema::fromFile('core', resource_path('settings/settings.schema.json'))];

        foreach ($this->modules->enabled() as $key) {
            if ($key === 'core' || ! $this->registry->has($key)) {
                continue;
            }

            $manifest = $this->registry->get($key);
            $schema = $manifest->raw['settings_schema'] ?? null;

            if (is_string($schema)) {
                $dir = is_dir($manifest->path) ? $manifest->path : dirname($manifest->path);
                $definitions[$key] = SettingsSchema::fromFile($key, $dir.DIRECTORY_SEPARATOR.$schema);
            }
        }

        return $this->definitions = $definitions;
    }

    public function definition(string $key): SettingDefinition
    {
        [$component, $name] = array_pad(explode('.', $key, 2), 2, '');

        foreach ($this->definitions()[$component] ?? [] as $definition) {
            if ($definition->name === $name) {
                return $definition;
            }
        }

        throw new InvalidArgumentException("Unknown setting {$key}.");
    }

    /** Current value: the tenant column, the stored value or the default. */
    public function get(string $key): mixed
    {
        $definition = $this->definition($key);

        if ($definition->tenantAttribute !== null) {
            return $this->tenants->current()->getAttribute($definition->tenantAttribute);
        }

        $stored = $this->stored();

        return array_key_exists($key, $stored) ? $stored[$key] : $definition->default;
    }

    /** @return array<string, mixed> name => value of one component */
    public function values(string $component): array
    {
        $values = [];

        foreach ($this->definitions()[$component] ?? [] as $definition) {
            $values[$definition->name] = $this->get($definition->key());
        }

        return $values;
    }

    /**
     * Validate and store the settings of one component; returns the names of
     * the changed settings.
     *
     * @param  array<string, mixed>  $input
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function save(string $component, array $input, User $actor): array
    {
        $definitions = $this->definitions()[$component] ?? throw new InvalidArgumentException("Unknown component {$component}.");
        $rules = [];

        foreach ($definitions as $definition) {
            $rules[$definition->name] = $definition->rules($this->allowed($definition));
        }

        $valid = Validator::make($input, $rules)->validate();
        $changed = [];

        DB::transaction(function () use ($component, $definitions, $valid, $actor, &$changed): void {
            foreach ($definitions as $definition) {
                $value = $definition->cast($valid[$definition->name] ?? null);

                if ($value === $this->get($definition->key())) {
                    continue;
                }

                $changed[] = $definition->name;
                $this->write($definition, $value, $actor);
            }

            if ($changed !== []) {
                $this->audit->record('settings.changed', null, ['component' => $component, 'keys' => $changed], $actor);
            }
        });

        $this->stored = null;

        return $changed;
    }

    /**
     * Values a setting with x-options accepts.
     *
     * @return list<string>|null
     */
    public function allowed(SettingDefinition $definition): ?array
    {
        return match ($definition->options) {
            'languages' => app(TenantLanguages::class)->locales()->available(),
            'formats' => Formats::offered(),
            'timezones' => DateTimeZone::listIdentifiers(),
            default => null,
        };
    }

    private function write(SettingDefinition $definition, mixed $value, User $actor): void
    {
        if ($definition->tenantAttribute !== null) {
            $this->tenants->current()->forceFill([$definition->tenantAttribute => $value])->save();

            return;
        }

        $row = Setting::query()->firstOrNew([
            'scope_type' => 'tenant',
            'scope_id' => '',
            'key' => $definition->key(),
        ]);

        // Back to the default: nothing stored, so a changed default applies.
        if ($value === $definition->default) {
            if ($row->exists) {
                $row->delete();
            }

            return;
        }

        $row->fill([
            'value' => $value,
            'lock_version' => $row->exists ? $row->lock_version + 1 : 1,
            'updated_by' => $actor->id,
        ])->save();
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        if ($this->stored === null) {
            $this->stored = [];

            foreach (Setting::query()->where('scope_type', 'tenant')->where('scope_id', '')->get(['key', 'value']) as $row) {
                $this->stored[$row->key] = $row->value;
            }
        }

        return $this->stored;
    }
}

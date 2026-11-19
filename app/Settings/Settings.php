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
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    /** @var list<SettingDefinition>|null core definitions, the same for every tenant */
    private ?array $core = null;

    /**
     * Per tenant, because TenantContext::run() may switch within one request or job.
     *
     * @var array<string, array<string, list<SettingDefinition>>>
     */
    private array $definitions = [];

    /** @var array<string, array<string, mixed>> */
    private array $stored = [];

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
        $tenant = $this->tenants->id();

        if (isset($this->definitions[$tenant])) {
            return $this->definitions[$tenant];
        }

        $definitions = ['core' => $this->core()];

        foreach ($this->modules->enabled() as $key) {
            if ($key === 'core' || ! $this->registry->has($key)) {
                continue;
            }

            $module = $this->moduleDefinitions($key);
            if ($module !== null) {
                $definitions[$key] = $module;
            }
        }

        return $this->definitions[$tenant] = $definitions;
    }

    public function definition(string $key): SettingDefinition
    {
        [$component, $name] = array_pad(explode('.', $key, 2), 2, '');

        // Core settings are read on every page; they never wait for module schemas.
        $candidates = $component === 'core' ? $this->core() : ($this->definitions()[$component] ?? []);

        foreach ($candidates as $definition) {
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

        if (! array_key_exists($key, $stored)) {
            return $definition->default;
        }

        return $definition->secret ? $this->reveal($key, $stored[$key]) : $stored[$key];
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

        $changes = [];

        DB::transaction(function () use ($component, $definitions, $valid, $actor, &$changed, &$changes): void {
            foreach ($definitions as $definition) {
                // An empty secret field keeps the stored secret; it is never shown again.
                if ($definition->secret && ($valid[$definition->name] ?? '') === '') {
                    continue;
                }

                $value = $definition->cast($valid[$definition->name] ?? null);
                $before = $this->get($definition->key());

                if ($value === $before) {
                    continue;
                }

                $changed[] = $definition->name;
                if (! $definition->secret) {
                    $changes[$definition->name] = ['from' => $before, 'to' => $value];
                }
                $this->write($definition, $value, $actor);
            }

            // Values make a change traceable; secrets are recorded by name only.
            if ($changed !== []) {
                $this->audit->record('settings.changed', null, ['component' => $component, 'keys' => $changed, 'changes' => $changes], $actor);
            }
        });

        unset($this->stored[$this->tenants->id()]);

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
            // Secrets: encrypted with the application key, which never lives in the database.
            'value' => $definition->secret && is_string($value) ? Crypt::encryptString($value) : $value,
            'lock_version' => $row->exists ? $row->lock_version + 1 : 1,
            'updated_by' => $actor->id,
        ])->save();
    }

    /** Whether a secret setting holds a value (the value itself is never shown). */
    public function isSet(string $key): bool
    {
        // A secret that no longer decrypts (other key) counts as not set: enter it again.
        return array_key_exists($key, $this->stored()) && $this->get($key) !== null;
    }

    private function reveal(string $key, mixed $encrypted): ?string
    {
        try {
            return is_string($encrypted) ? Crypt::decryptString($encrypted) : null;
        } catch (DecryptException) {
            // Another application key: the secret must be entered again.
            Log::warning('Secret setting cannot be decrypted', ['key' => $key]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        $tenant = $this->tenants->id();

        if (! isset($this->stored[$tenant])) {
            $this->stored[$tenant] = [];

            foreach (Setting::query()->where('scope_type', 'tenant')->where('scope_id', '')->get(['key', 'value']) as $row) {
                $this->stored[$tenant][$row->key] = $row->value;
            }
        }

        return $this->stored[$tenant];
    }

    /** @return list<SettingDefinition> */
    private function core(): array
    {
        return $this->core ??= SettingsSchema::fromFile('core', resource_path('settings/settings.schema.json'));
    }

    /**
     * Definitions of one module, or null when it declares none or its schema
     * is unusable. A broken schema hides only that module's settings and is
     * logged; it never breaks the pages of the tenant. The file must lie
     * inside the module's directory.
     *
     * @return list<SettingDefinition>|null
     */
    private function moduleDefinitions(string $key): ?array
    {
        $manifest = $this->registry->get($key);
        $schema = $manifest->raw['settings_schema'] ?? null;

        if (! is_string($schema)) {
            return null;
        }

        $dir = realpath(is_dir($manifest->path) ? $manifest->path : dirname($manifest->path));
        $file = realpath(($dir ?: '').DIRECTORY_SEPARATOR.$schema);

        try {
            if ($dir === false || $file === false || ! str_starts_with($file, $dir.DIRECTORY_SEPARATOR) || ! is_file($file)) {
                throw new InvalidArgumentException('settings_schema must name a file inside the module directory.');
            }

            return SettingsSchema::fromFile($key, $file);
        } catch (InvalidArgumentException $e) {
            Log::error('Module settings schema refused', ['module' => $key, 'reason' => $e->getMessage()]);

            return null;
        }
    }
}

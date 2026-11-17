<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

use App\Models\TenantModule;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant module switches for the current tenant (S-0901).
 *
 * Enabling also enables required modules; the caller shows that plan to the
 * admin first (enablePlan). Disabling is refused while enabled modules depend
 * on the module. Neither touches module data.
 */
final class TenantModules
{
    /** @var array<string, list<string>> tenant id => enabled keys */
    private array $cache = [];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return list<string> enabled module keys of the current tenant, in boot order
     */
    public function enabled(): array
    {
        return $this->cache[$this->tenant->id()] ??= $this->load();
    }

    /**
     * @return list<string>
     */
    private function load(): array
    {
        $keys = TenantModule::query()->where('enabled', true)->pluck('module_key')->all();

        return array_values(array_filter(
            array_keys($this->registry->all()),
            fn (string $key): bool => in_array($key, $keys, true),
        ));
    }

    public function isEnabled(string $key): bool
    {
        return $key === ModuleManifest::CORE || in_array($key, $this->enabled(), true);
    }

    /**
     * What enabling $key would switch on, dependencies first.
     *
     * @return list<string>
     */
    public function enablePlan(string $key): array
    {
        return $this->registry->enablePlan($key, $this->enabled());
    }

    /**
     * Enable $key and everything it requires. Returns the modules switched on.
     *
     * @return list<string>
     */
    public function enable(string $key, ?User $actor = null): array
    {
        $plan = $this->enablePlan($key);

        /** @var list<string> $first */
        $first = DB::transaction(function () use ($plan, $actor): array {
            $first = [];
            foreach ($plan as $module) {
                $row = TenantModule::query()->updateOrCreate(
                    ['module_key' => $module],
                    ['enabled' => true, 'enabled_at' => now(), 'disabled_at' => null, 'changed_by' => $actor?->id],
                );

                if ($row->wasRecentlyCreated) {
                    $first[] = $module;
                }
            }

            return $first;
        });

        unset($this->cache[$this->tenant->id()]);

        foreach ($plan as $module) {
            event(new ModuleEnabled($this->tenant->id(), $module, in_array($module, $first, true), $actor?->id));
        }

        return $plan;
    }

    /**
     * @throws ModuleStillRequired
     */
    public function disable(string $key, ?User $actor = null): void
    {
        $dependents = $this->registry->dependentsOf($key, $this->enabled());

        if ($dependents !== []) {
            throw new ModuleStillRequired($key, $dependents);
        }

        TenantModule::query()
            ->where('module_key', $key)
            ->update(['enabled' => false, 'disabled_at' => now(), 'changed_by' => $actor?->id]);

        unset($this->cache[$this->tenant->id()]);

        event(new ModuleDisabled($this->tenant->id(), $key, $actor?->id));
    }
}

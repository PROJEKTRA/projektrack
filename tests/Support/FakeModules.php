<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Tests\Support;

use App\Authorization\PermissionCatalog;
use App\Modules\ModuleRegistry;
use Psr\Log\NullLogger;

/**
 * Installs synthetic modules for one test: writes their module.json files to
 * a temporary directory and rebuilds the registry and permission catalog.
 */
final class FakeModules
{
    /**
     * @param  array<string, array<string, mixed>>  $modules  key => manifest fields (key, name and version are filled in)
     */
    public static function install(array $modules): void
    {
        $root = sys_get_temp_dir().'/ptk-mod-'.bin2hex(random_bytes(6));

        foreach ($modules as $key => $manifest) {
            mkdir("{$root}/{$key}", recursive: true);
            file_put_contents("{$root}/{$key}/module.json", (string) json_encode([
                'key' => $key,
                'name_string' => "{$key}:module_name",
                'version' => '0.9.0',
                'core' => '^0.9',
                'edition' => 'community',
                'requires' => ['core'],
                'permissions' => [],
                ...$manifest,
            ]));
        }

        app()->instance(ModuleRegistry::class, new ModuleRegistry([$root], new NullLogger));
        app()->forgetInstance(PermissionCatalog::class);
        app()->forgetScopedInstances();
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Api;

use App\Modules\ModuleRegistry;
use App\Modules\TenantModules;

/**
 * Scopes a token may carry (S-0913): "<component>:<access>", for example
 * core:read or timesheets:read. A scope only narrows what a token may do;
 * every request still needs the user's own permissions (same as the UI).
 * Modules declare theirs in module.json ("api_scopes"), under their own key.
 */
final class ApiScopes
{
    /** @var array<string, string> scope => label string identifier */
    public const array CORE = [
        'core:read' => 'core:api_tokens.scopes.core_read',
        'core:write' => 'core:api_tokens.scopes.core_write',
        'core:admin' => 'core:api_tokens.scopes.core_admin',
    ];

    public function __construct(
        private readonly TenantModules $modules,
        private readonly ModuleRegistry $registry,
    ) {}

    /** @return array<string, string> scope => label identifier */
    public function all(): array
    {
        $scopes = self::CORE;

        foreach ($this->modules->enabled() as $key) {
            if (! $this->registry->has($key)) {
                continue;
            }

            foreach ((array) ($this->registry->get($key)->raw['api_scopes'] ?? []) as $declared) {
                if (is_array($declared) && is_string($declared['scope'] ?? null) && is_string($declared['label_string'] ?? null)
                    && preg_match('/^'.preg_quote($key, '/').':[a-z_]+$/', $declared['scope']) === 1) {
                    $scopes[$declared['scope']] = $declared['label_string'];
                }
            }
        }

        return $scopes;
    }
}

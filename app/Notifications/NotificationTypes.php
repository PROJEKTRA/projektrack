<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use App\Modules\ModuleRegistry;
use App\Modules\TenantModules;

/**
 * Notification types users can see and choose e-mail for (S-0912): the
 * core's, and those of every enabled module (module.json "notifications":
 * [{"type": "timesheets.submitted", "label_string": "timesheets:..."}]).
 * Only core types can be mandatory (security notices, always e-mailed); a
 * module's "mandatory" flag is ignored, so no module can force e-mail.
 */
final class NotificationTypes
{
    /** @var list<array{type: string, label: string, mandatory: bool}> */
    public const array CORE = [
        ['type' => 'core.two_factor_reset', 'label' => 'core:notifications.types.two_factor_reset', 'mandatory' => true],
    ];

    public function __construct(
        private readonly TenantModules $modules,
        private readonly ModuleRegistry $registry,
    ) {}

    /** @return list<array{type: string, label: string, mandatory: bool}> */
    public function all(): array
    {
        $types = self::CORE;

        foreach ($this->modules->enabled() as $key) {
            if (! $this->registry->has($key)) {
                continue;
            }

            foreach ((array) ($this->registry->get($key)->raw['notifications'] ?? []) as $declared) {
                if (is_array($declared) && is_string($declared['type'] ?? null) && str_starts_with($declared['type'], "{$key}.") && is_string($declared['label_string'] ?? null)) {
                    $types[] = [
                        'type' => $declared['type'],
                        'label' => $declared['label_string'],
                        // Only core security notices override a user's or the organization's choice.
                        'mandatory' => false,
                    ];
                }
            }
        }

        return $types;
    }

    /** @return array{type: string, label: string, mandatory: bool}|null */
    public function get(string $type): ?array
    {
        foreach ($this->all() as $declared) {
            if ($declared['type'] === $type) {
                return $declared;
            }
        }

        return null;
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\SharedLinks;

use InvalidArgumentException;

/**
 * The shareable object types of the modules (S-0987). A singleton; modules
 * register their target in their service provider. A type belongs to one
 * module for good: links store it.
 */
final class SharedLinkTargets
{
    /** @var array<string, SharedLinkTarget> */
    private array $targets = [];

    public function register(SharedLinkTarget $target): void
    {
        $type = $target->type();

        if (preg_match('/^[a-z][a-z0-9_]{1,59}$/', $type) !== 1) {
            throw new InvalidArgumentException("Invalid shared link target type [{$type}].");
        }
        if (isset($this->targets[$type])) {
            throw new InvalidArgumentException("Shared link target [{$type}] is registered already.");
        }
        if (($target->actions()['view'] ?? null) !== false) {
            throw new InvalidArgumentException("Shared link target [{$type}] must offer view as a reading action.");
        }

        $this->targets[$type] = $target;
    }

    public function find(string $type): ?SharedLinkTarget
    {
        return $this->targets[$type] ?? null;
    }

    public function get(string $type): SharedLinkTarget
    {
        return $this->find($type) ?? throw new InvalidArgumentException("Unknown shared link target [{$type}].");
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

/**
 * Access to one field of an entity, ordered from least to most.
 */
enum FieldAccess: string
{
    case Hidden = 'hidden';
    case Read = 'read';
    case Write = 'write';

    public function rank(): int
    {
        return match ($this) {
            self::Hidden => 0,
            self::Read => 1,
            self::Write => 2,
        };
    }

    public function max(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }

    public function min(self $other): self
    {
        return $other->rank() < $this->rank() ? $other : $this;
    }

    public function canRead(): bool
    {
        return $this !== self::Hidden;
    }
}

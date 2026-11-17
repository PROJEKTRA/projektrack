<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization\Events;

/**
 * A user got or lost a global role (data model, RoleChanged). The audit log
 * (S-0907) records it.
 */
final readonly class UserRolesChanged
{
    public function __construct(
        public string $tenantId,
        public string $userId,
        public string $roleId,
        public bool $assigned,
        public ?string $actorId,
    ) {}
}

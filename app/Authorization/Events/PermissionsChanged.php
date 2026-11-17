<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization\Events;

/**
 * Grants of a role or project role changed, or a field override was set
 * (data model, PermissionsChanged). The audit log (S-0907) records it.
 */
final readonly class PermissionsChanged
{
    /**
     * @param  'role'|'project_role'|'field'  $kind
     * @param  list<string>  $granted
     * @param  list<string>  $revoked
     */
    public function __construct(
        public string $tenantId,
        public string $kind,
        public string $roleId,
        public array $granted,
        public array $revoked,
        public ?string $actorId,
    ) {}
}

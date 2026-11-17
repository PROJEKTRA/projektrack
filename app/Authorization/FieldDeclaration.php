<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

/**
 * A sensitive field declared in a module.json.
 *
 * $permission optionally names a module permission that grants read access to
 * the field (for example "timesheets.view_rates" for every rate and amount of a
 * time entry), so admins grant one permission instead of twenty fields.
 */
final readonly class FieldDeclaration
{
    public function __construct(
        public string $entity,
        public string $field,
        public FieldAccess $default,
        public FieldRisk $risk,
        public ?string $permission = null,
        public string $moduleKey = '',
    ) {}

    public function withModule(string $moduleKey): self
    {
        return new self($this->entity, $this->field, $this->default, $this->risk, $this->permission, $moduleKey);
    }
}

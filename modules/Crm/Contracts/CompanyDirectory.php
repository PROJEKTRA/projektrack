<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Contracts;

/**
 * What other modules may ask about customers of the current tenant.
 */
interface CompanyDirectory
{
    /** @return list<array{id: string, number: string, name: string}> active customers by name */
    public function choices(): array;

    public function isActive(string $id): bool;

    /**
     * @param  list<string>  $ids
     * @return array<string, string> id => name
     */
    public function names(array $ids): array;
}

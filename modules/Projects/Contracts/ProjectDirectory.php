<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Contracts;

/**
 * What other modules may ask about projects of the current tenant.
 *
 * @phpstan-type Bookable array{id: string, code: string, name: string, billable: bool, project_id: string, project: string, company_id: string}
 */
interface ProjectDirectory
{
    /** @return list<Bookable> open work packages in active projects the user is a member of */
    public function bookable(string $userId, string $onDate): array;

    /** Whether the user may book on this work package on that day. */
    public function mayBook(string $userId, string $workPackageId, string $onDate): bool;

    /**
     * Any work package, open or not, for display.
     *
     * @param  list<string>  $ids
     * @return array<string, Bookable>
     */
    public function workPackages(array $ids): array;

    /**
     * @param  list<string>  $ids
     * @return array<string, array{id: string, name: string, number: string, company_id: string, currency: string, billing_model: string}>
     */
    public function projects(array $ids): array;

    /** @return list<array{id: string, name: string, number: string, company_id: string, currency: string, billing_model: string}> active projects by name */
    public function active(): array;

    /** @return list<array{id: string, name: string, number: string, company_id: string, currency: string, billing_model: string}> every project, also closed ones, by name */
    public function all(): array;

    /** The contract of a work package, if any. */
    public function contractOf(string $workPackageId): ?string;

    /** @return array{id: string, number: string, project_id: string}|null */
    public function contract(string $id): ?array;

    /** @return array{rounding: int, minimum: int} minutes, from the contract of the work package */
    public function rounding(string $workPackageId): array;
}

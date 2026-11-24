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

    /**
     * Everything a document needs, as it is on the day.
     *
     * @return array{id: string, number: string, name: string, document_locale: string, currency: string, payment_days: int, tax: array{tax_status: string, country_code: string, vat_id: string|null, tax_number: string|null}|null, address: array{line1: string, line2: string|null, postal_code: string|null, city: string, region: string|null, country_code: string}|null}|null
     */
    public function billing(string $id, string $onDate): ?array;

    /** @return list<string> addresses of contacts that receive invoices */
    public function invoiceRecipients(string $id): array;

    public function isActive(string $id): bool;

    /**
     * @param  list<string>  $ids
     * @return array<string, string> id => name
     */
    public function names(array $ids): array;
}

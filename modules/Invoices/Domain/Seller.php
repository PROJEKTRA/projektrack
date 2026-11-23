<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\Settings\Settings;
use App\Tenancy\TenantContext;

/**
 * The issuing company, from Settings > Invoicing and the tenant.
 */
final class Seller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly TenantContext $tenant,
    ) {}

    /** @return array{name: string, street: string, postal_code: string, city: string, country: string, tax_number: string, vat_id: string, vat_status: string, bank: string, iban: string, bic: string, contact: string} */
    public function data(): array
    {
        $get = function (string $key): string {
            $value = $this->settings->get("invoices.{$key}");

            return is_string($value) ? trim($value) : '';
        };

        return [
            'name' => $get('legal_name'), 'street' => $get('street'), 'postal_code' => $get('postal_code'), 'city' => $get('city'), 'country' => $this->tenant->current()->country_code,
            'tax_number' => $get('tax_number'), 'vat_id' => $get('vat_id'), 'vat_status' => $get('vat_status'), 'bank' => $get('bank'), 'iban' => $get('iban'), 'bic' => $get('bic'), 'contact' => $get('contact'),
        ];
    }

    public function textsConfirmed(): bool
    {
        return $this->settings->get('invoices.legal_texts_confirmed') === true;
    }
}

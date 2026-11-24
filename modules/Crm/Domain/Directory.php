<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Domain;

use App\Support\Lists;
use Modules\Crm\Contracts\CompanyDirectory;

final class Directory implements CompanyDirectory
{
    public function choices(): array
    {
        return array_values(Company::query()->where('status', 'active')->orderBy('name')->get()
            ->map(fn (Company $c): array => ['id' => $c->id, 'number' => $c->number, 'name' => $c->name])->all());
    }

    public function billing(string $id, string $onDate): ?array
    {
        $c = Company::query()->find($id);
        if ($c === null) {
            return null;
        }
        $tax = $c->taxProfiles()->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $onDate))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $onDate))->first();
        $a = Address::query()->where('owner_type', 'company')->where('owner_id', $c->id)->first();

        return [
            'id' => $c->id, 'number' => $c->number, 'name' => $c->legal_name ?? $c->name, 'document_locale' => $c->document_locale, 'currency' => $c->currency, 'payment_days' => $c->payment_days,
            'tax' => $tax === null ? null : ['tax_status' => $tax->tax_status, 'country_code' => $tax->country_code, 'vat_id' => $tax->vat_id, 'tax_number' => $tax->tax_number],
            'address' => $a === null ? null : ['line1' => $a->line1, 'line2' => $a->line2, 'postal_code' => $a->postal_code, 'city' => $a->city, 'region' => $a->region, 'country_code' => $a->country_code],
        ];
    }

    public function invoiceRecipients(string $id): array
    {
        return Lists::strings(Contact::query()->where('company_id', $id)->where('invoice_recipient', true)->whereNotNull('email_normalized')->orderBy('email_normalized')->pluck('email_normalized'));
    }

    public function isActive(string $id): bool
    {
        return Company::query()->whereKey($id)->where('status', 'active')->exists();
    }

    public function names(array $ids): array
    {
        $names = [];
        foreach (Company::query()->whereIn('id', Lists::strings($ids))->get() as $company) {
            $names[$company->id] = $company->name;
        }

        return $names;
    }
}

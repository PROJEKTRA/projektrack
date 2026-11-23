<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Domain;

use App\Audit\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a customer with everything that belongs to it, together or not at all
 * (S-0939). Used by the dialog and by the API.
 *
 * @phpstan-type AddressData array{line1: string, line2?: string|null, postal_code?: string|null, city: string, region?: string|null, country_code: string}
 * @phpstan-type CompanyData array{
 *     company: array<string, mixed>,
 *     tax?: array{tax_status: string, country_code: string, vat_id?: string|null, tax_number?: string|null, valid_from?: string|null},
 *     address?: AddressData,
 *     contacts?: list<array<string, mixed>>,
 *     sites?: list<array{id?: string|null, name: string, address: AddressData}>
 * }
 */
final class Companies
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLog $audit,
    ) {}

    /** @param CompanyData $data */
    public function create(array $data, User $actor): Company
    {
        return DB::transaction(function () use ($data, $actor): Company {
            // One numbering at a time per tenant.
            Tenant::query()->whereKey($this->tenant->id())->lockForUpdate()->first();
            $last = (int) substr(Company::query()->orderByDesc('number')->first()->number ?? 'C-0', 2);

            $company = Company::query()->create([...$data['company'], 'number' => sprintf('C-%05d', $last + 1), 'status' => 'active']);
            $this->saveParts($company, $data);
            $this->audit->record('crm.company.created', $company, ['number' => $company->number], $actor);

            return $company;
        }, 3);
    }

    /** @param CompanyData $data */
    public function update(Company $company, array $data, User $actor): Company
    {
        return DB::transaction(function () use ($company, $data, $actor): Company {
            $locked = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $locked->fill($data['company']);
            $fields = array_keys($locked->getDirty());
            $locked->save();
            $parts = $this->saveParts($locked, $data);
            if ($fields !== [] || $parts !== []) {
                // Names of fields only: the audit log holds no personal data of contacts.
                $this->audit->record('crm.company.updated', $locked, ['fields' => $fields, 'parts' => $parts], $actor);
            }

            return $locked;
        }, 3);
    }

    /**
     * @param  CompanyData  $data
     * @return list<string> parts that were saved
     */
    private function saveParts(Company $company, array $data): array
    {
        $parts = [];
        if (isset($data['tax'])) {
            $this->saveTax($company, $data['tax']);
            $parts[] = 'tax';
        }
        if (isset($data['address'])) {
            $this->saveAddress('company', $company->id, 'billing', $data['address']);
            $parts[] = 'address';
        }
        if (isset($data['contacts'])) {
            $this->saveContacts($company, $data['contacts']);
            $parts[] = 'contacts';
        }
        if (isset($data['sites'])) {
            $this->saveSites($company, $data['sites']);
            $parts[] = 'sites';
        }

        return $parts;
    }

    /** @param array{tax_status: string, country_code: string, vat_id?: string|null, tax_number?: string|null, valid_from?: string|null} $tax */
    private function saveTax(Company $company, array $tax): void
    {
        $values = ['tax_status' => $tax['tax_status'], 'country_code' => $tax['country_code'], 'vat_id' => $tax['vat_id'] ?? null, 'tax_number' => $tax['tax_number'] ?? null];
        $current = $company->taxProfiles()->whereNull('valid_to')->first();
        if ($current !== null && array_all($values, fn (?string $value, string $key): bool => $current->getAttribute($key) === $value)) {
            return;
        }

        $from = $tax['valid_from'] ?? null;
        // Without a date it is a correction: the profile was wrong from its start.
        if ($current !== null && ($from === null || $current->valid_from?->toDateString() === $from)) {
            $current->update($values);

            return;
        }
        if ($current !== null && $current->valid_from !== null && $from < $current->valid_from->toDateString()) {
            throw ValidationException::withMessages(['tax.valid_from' => __('crm:errors.tax_valid_from')]);
        }
        $current?->update(['valid_to' => CarbonImmutable::parse((string) $from, 'UTC')->subDay()->toDateString()]);
        $company->taxProfiles()->create([...$values, 'valid_from' => $current === null ? null : $from]);
    }

    /** @param AddressData $address */
    private function saveAddress(string $type, string $id, string $kind, array $address): void
    {
        Address::query()->updateOrCreate(['owner_type' => $type, 'owner_id' => $id, 'kind' => $kind], [
            'line2' => null, 'postal_code' => null, 'region' => null, ...$address,
        ]);
    }

    /** @param list<array<string, mixed>> $contacts */
    private function saveContacts(Company $company, array $contacts): void
    {
        $kept = [];
        foreach ($contacts as $contact) {
            if (isset($contact['id'])) {
                $kept[] = $contact['id'];
            }
        }
        $company->contacts()->whereNotIn('id', $kept)->delete();
        // Emptied first, so two contacts can swap their addresses.
        $company->contacts()->update(['email_normalized' => null]);

        foreach ($contacts as $contact) {
            $email = $contact['email'] ?? null;
            $values = [
                'first_name' => $contact['first_name'] ?? null,
                'last_name' => $contact['last_name'],
                'email_normalized' => is_string($email) && $email !== '' ? mb_strtolower(trim($email)) : null,
                'phone' => $contact['phone'] ?? null,
                'position' => $contact['position'] ?? null,
                'invoice_recipient' => (bool) ($contact['invoice_recipient'] ?? false),
            ];
            isset($contact['id'])
                ? $company->contacts()->whereKey($contact['id'])->firstOrFail()->update($values)
                : $company->contacts()->create($values);
        }
    }

    /** @param list<array{id?: string|null, name: string, address: AddressData}> $sites */
    private function saveSites(Company $company, array $sites): void
    {
        $kept = array_values(array_filter(array_column($sites, 'id')));
        $gone = $company->sites()->whereNotIn('id', $kept)->pluck('id')->all();
        Address::query()->where('owner_type', 'site')->whereIn('owner_id', $gone)->delete();
        $company->sites()->whereIn('id', $gone)->delete();

        foreach ($sites as $data) {
            if (isset($data['id'])) {
                $site = $company->sites()->whereKey($data['id'])->firstOrFail();
                $site->update(['name' => $data['name']]);
            } else {
                $site = $company->sites()->create(['name' => $data['name']]);
            }
            $this->saveAddress('site', $site->id, 'site', $data['address']);
        }
    }

    /** @return array<string, mixed> everything the dialog and the API show */
    public function describe(Company $company): array
    {
        $tax = $company->taxProfiles()->whereNull('valid_to')->first();
        $billing = Address::query()->where('owner_type', 'company')->where('owner_id', $company->id)->first();
        $sites = $company->sites()->get();
        $addresses = Address::query()->where('owner_type', 'site')->whereIn('owner_id', $sites->pluck('id'))->get()->keyBy('owner_id');

        return [
            ...$company->only(['id', 'number', 'name', 'legal_name', 'kind', 'status', 'document_locale', 'currency', 'payment_days', 'einvoice_format', 'leitweg_id']),
            'tax' => $tax?->only(['tax_status', 'country_code', 'vat_id', 'tax_number']),
            'address' => $billing?->only(Address::FIELDS),
            'contacts' => $company->contacts()->get()->map(fn (Contact $contact): array => [
                ...$contact->only(['id', 'first_name', 'last_name', 'phone', 'position', 'invoice_recipient']),
                'email' => $contact->email_normalized,
            ])->all(),
            'sites' => $sites->map(fn (CompanySite $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'address' => $addresses->get($site->id)?->only(Address::FIELDS),
            ])->all(),
        ];
    }
}

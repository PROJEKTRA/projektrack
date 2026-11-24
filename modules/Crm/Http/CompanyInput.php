<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Http;

use App\Domain\Money\Amount;
use App\I18n\Locales;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as Validation;
use Modules\Crm\Domain\Companies;
use Modules\Crm\Domain\Company;
use Modules\Crm\Domain\CompanyTaxProfile;
use Modules\Crm\Domain\LeitwegId;
use Modules\Crm\Domain\VatId;

/**
 * Checks what the dialog and the API send for a customer. Contacts and tax data
 * need their own permission; leaving them out keeps what is stored.
 *
 * @phpstan-import-type CompanyData from Companies
 */
final class CompanyInput
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  array<array-key, mixed>  $input
     * @return CompanyData
     */
    public function validate(array $input, User $user, bool $creating): array
    {
        abort_if(array_key_exists('contacts', $input) && ! $user->can('crm.contacts.manage'), 403);
        abort_if(($creating || array_key_exists('tax', $input)) && ! $user->can('crm.tax_profile.manage'), 403);

        if (is_array($input['tax'] ?? null) && is_string($input['tax']['vat_id'] ?? null)) {
            $input['tax']['vat_id'] = VatId::normalize($input['tax']['vat_id']) ?: null;
        }
        foreach ([['tax', 'country_code'], ['address', 'country_code'], ['currency']] as $path) {
            $value = Arr::get($input, implode('.', $path));
            if (is_string($value)) {
                Arr::set($input, implode('.', $path), strtoupper(trim($value)));
            }
        }

        $country = ['string', 'regex:/^[A-Z]{2}\z/'];
        $text = fn (int $max): array => ['nullable', 'string', "max:{$max}"];
        $address = fn (string $prefix, string $parent): array => [
            "{$prefix}.line1" => ["required_with:{$parent}", 'string', 'max:191'],
            "{$prefix}.line2" => $text(191),
            "{$prefix}.postal_code" => $text(20),
            "{$prefix}.city" => ["required_with:{$parent}", 'string', 'max:120'],
            "{$prefix}.region" => $text(120),
            "{$prefix}.country_code" => ["required_with:{$parent}", ...$country],
        ];
        $must = $creating ? 'required' : 'sometimes';

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:191'],
            'legal_name' => $text(191),
            'kind' => ['required', Rule::in(Company::KINDS)],
            'status' => ['sometimes', Rule::in(Company::STATUSES)],
            'document_locale' => ['required', 'string', Rule::in($this->locales->available())],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}\z/', fn (string $attribute, mixed $value, callable $fail) => is_string($value) && Amount::isCurrency($value) ?: $fail(__('validation.in', ['attribute' => $attribute]))],
            'payment_days' => ['required', 'integer', 'between:0,365'],
            'einvoice_format' => ['required', Rule::in(Company::EINVOICE_FORMATS)],
            'leitweg_id' => ['nullable', 'string', 'max:46', function (string $attribute, mixed $value, callable $fail): void {
                if (! is_string($value) || ! LeitwegId::isValid($value)) {
                    $fail(__('crm:errors.leitweg_id'));
                }
            }],
            'tax' => [$must, 'array:tax_status,country_code,vat_id,tax_number,valid_from'],
            'tax.tax_status' => ['required_with:tax', Rule::in(CompanyTaxProfile::STATUSES)],
            'tax.country_code' => ['required_with:tax', ...$country],
            'tax.vat_id' => $text(20),
            'tax.tax_number' => $text(40),
            'tax.valid_from' => ['nullable', 'date_format:Y-m-d'],
            'address' => [$must, 'array:line1,line2,postal_code,city,region,country_code'],
            ...$address('address', 'address'),
            'contacts' => ['sometimes', 'array', 'max:200'],
            'contacts.*' => ['array:id,first_name,last_name,email,phone,position,invoice_recipient'],
            'contacts.*.id' => ['nullable', 'ulid', 'distinct'],
            'contacts.*.first_name' => $text(120),
            'contacts.*.last_name' => ['required', 'string', 'max:120'],
            'contacts.*.email' => ['nullable', 'email:rfc', 'max:191', 'distinct:ignore_case'],
            'contacts.*.phone' => $text(40),
            'contacts.*.position' => $text(120),
            'contacts.*.invoice_recipient' => ['boolean'],
            'sites' => ['sometimes', 'array', 'max:100'],
            'sites.*' => ['array:id,name,address'],
            'sites.*.id' => ['nullable', 'ulid', 'distinct'],
            'sites.*.name' => ['required', 'string', 'max:191'],
            'sites.*.address' => ['required', 'array:line1,line2,postal_code,city,region,country_code'],
            ...$address('sites.*.address', 'sites.*.address'),
        ]);
        $validator->after(fn (Validation $v) => $this->checkTax($v, $input));
        $data = $validator->validate();

        /** @var CompanyData */
        return [
            'company' => Arr::only($data, ['name', 'legal_name', 'kind', 'status', 'document_locale', 'currency', 'payment_days', 'einvoice_format', 'leitweg_id']),
            ...Arr::only($data, ['tax', 'address', 'contacts', 'sites']),
        ];
    }

    /** @param array<array-key, mixed> $input */
    private function checkTax(Validation $validator, array $input): void
    {
        $tax = $input['tax'] ?? null;
        if (! is_array($tax) || ! is_string($tax['tax_status'] ?? null) || ! is_string($tax['country_code'] ?? null)) {
            return;
        }
        $country = $tax['country_code'];
        $vatId = $tax['vat_id'] ?? null;
        $home = $this->tenant->current()->country_code;
        $isEu = in_array($country, VatId::euCountries(), true);

        $fits = match ($tax['tax_status']) {
            'domestic' => $country === $home,
            'eu_business' => $isEu && $country !== $home,
            'non_eu_business' => ! $isEu && $country !== $home,
            default => true,
        };
        if (! $fits) {
            $validator->errors()->add('tax.tax_status', __('crm:errors.tax_status_country'));
        }
        if ($tax['tax_status'] === 'eu_business' && ($vatId === null || $vatId === '')) {
            $validator->errors()->add('tax.vat_id', __('crm:errors.vat_id_required'));
        } elseif (is_string($vatId) && $vatId !== '' && ! VatId::isValid($vatId, $country)) {
            $validator->errors()->add('tax.vat_id', __('crm:errors.vat_id_format'));
        }
    }
}

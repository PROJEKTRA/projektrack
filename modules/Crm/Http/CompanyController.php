<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Http;

use App\Http\Controllers\Controller;
use App\I18n\Locales;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Domain\Address;
use Modules\Crm\Domain\Companies;
use Modules\Crm\Domain\Company;
use Modules\Crm\Domain\CompanyTaxProfile;

/**
 * Customers (S-0939): list, details and the one dialog that saves everything.
 */
class CompanyController extends Controller
{
    public function index(Request $request, Locales $locales, TenantContext $tenant): Response
    {
        $search = trim($request->string('search')->limit(100, '')->toString());
        $status = $request->query('status') === 'archived' ? 'archived' : 'active';
        $companies = CompanySearch::query($search, $status)->paginate(50)->withQueryString();
        $cities = Address::query()->where('owner_type', 'company')->whereIn('owner_id', $companies->pluck('id'))->get()->keyBy('owner_id');

        return Inertia::render('Crm::Companies', [
            'companies' => $companies->through(fn (Company $company): array => [
                ...$company->only(['id', 'number', 'name', 'kind']),
                'city' => $cities->get($company->id)?->city,
                'country' => $cities->get($company->id)?->country_code,
            ]),
            'search' => $search,
            'status' => $status,
            'form' => $this->form($this->currentUser($request), $locales, $tenant),
        ]);
    }

    public function show(Request $request, string $company, Companies $companies, Locales $locales, TenantContext $tenant): Response
    {
        return Inertia::render('Crm::Company', [
            'company' => $companies->describe(Company::query()->findOrFail($company)),
            'form' => $this->form($this->currentUser($request), $locales, $tenant),
        ]);
    }

    public function store(Request $request, CompanyInput $input, Companies $companies): RedirectResponse
    {
        $user = $this->currentUser($request);
        $company = $companies->create($input->validate($request->all(), $user, true), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('crm:saved')]);

        return to_route('crm.companies.show', $company->id);
    }

    public function update(Request $request, string $company, CompanyInput $input, Companies $companies): RedirectResponse
    {
        $user = $this->currentUser($request);
        $companies->update(Company::query()->findOrFail($company), $input->validate($request->all(), $user, false), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('crm:saved')]);

        return to_route('crm.companies.show', $company);
    }

    /** @return array<string, mixed> choices and rights the dialog needs */
    private function form(User $user, Locales $locales, TenantContext $tenant): array
    {
        $home = $tenant->current();

        return [
            'kinds' => Company::KINDS,
            'taxStatuses' => CompanyTaxProfile::STATUSES,
            'einvoiceFormats' => Company::EINVOICE_FORMATS,
            'locales' => array_map(fn (string $locale): array => ['code' => $locale, 'name' => locale_get_display_name($locale, $locale) ?: $locale], $locales->available()),
            'defaults' => ['country' => $home->country_code, 'currency' => $home->base_currency, 'locale' => $home->default_locale],
            'can' => [
                'manage' => $user->can('crm.manage'),
                'create' => $user->can('crm.manage') && $user->can('crm.tax_profile.manage'),
                'contacts' => $user->can('crm.contacts.manage'),
                'tax' => $user->can('crm.tax_profile.manage'),
            ],
        ];
    }
}

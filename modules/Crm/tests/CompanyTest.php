<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Api\ApiTokens;
use App\Authorization\RoleManager;
use App\I18n\StringCatalog;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia;
use Modules\Crm\Domain\Address;
use Modules\Crm\Domain\Companies;
use Modules\Crm\Domain\Company;
use Modules\Crm\Domain\CompanyTaxProfile;
use Modules\Crm\Domain\Contact;
use Modules\Crm\Domain\LeitwegId;
use Modules\Crm\Domain\VatId;

/*
 * S-0939: customers and contacts.
 */

beforeEach(function () {
    $this->tenant->update(['country_code' => 'BA', 'base_currency' => 'BAM', 'timezone' => 'Europe/Sarajevo']);
    app(TenantModules::class)->enable('crm');
});

function crmUser(string $role): User
{
    $user = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($user, Role::query()->where('key', $role)->firstOrFail());

    return $user;
}

/** @return array<string, mixed> */
function companyInput(array $changes = []): array
{
    return array_replace([
        'name' => 'Learnteq Demo GmbH', 'legal_name' => null, 'kind' => 'customer', 'document_locale' => 'en', 'currency' => 'eur',
        'payment_days' => 14, 'einvoice_format' => 'zugferd', 'leitweg_id' => null,
        'tax' => ['tax_status' => 'eu_business', 'country_code' => 'DE', 'vat_id' => 'DE 999 999 999', 'tax_number' => null],
        'address' => ['line1' => 'Teststr. 1', 'city' => 'Stuttgart', 'postal_code' => '70173', 'country_code' => 'de'],
        'contacts' => [['last_name' => 'Example', 'email' => 'Erika@Example.test', 'invoice_recipient' => true]],
        'sites' => [['name' => 'Plant', 'address' => ['line1' => 'Werkstr. 2', 'city' => 'Ulm', 'country_code' => 'DE']]],
    ], $changes);
}

it('creates a customer with address, tax profile, contact and site in one request', function () {
    $this->actingAs(crmUser('controller'))->post('/crm/companies', companyInput())->assertSessionHasNoErrors();

    $company = Company::query()->sole();
    expect($company->number)->toBe('C-00001')
        ->and($company->currency)->toBe('EUR')
        ->and($company->payment_days)->toBe(14)
        ->and(CompanyTaxProfile::query()->sole()->vat_id)->toBe('DE999999999')
        ->and(Contact::query()->sole()->email_normalized)->toBe('erika@example.test')
        ->and(Contact::query()->sole()->invoice_recipient)->toBeTrue()
        ->and(Address::query()->count())->toBe(2)
        ->and(AuditEntry::query()->where('action', 'crm.company.created')->count())->toBe(1);

    $this->get("/crm/companies/{$company->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Crm::Company', false)
        ->where('company.address.city', 'Stuttgart')
        ->where('company.sites.0.address.city', 'Ulm'));
});

it('numbers customers one after another and keeps numbers of archived ones', function () {
    $user = crmUser('controller');
    $this->actingAs($user)->post('/crm/companies', companyInput());
    Company::query()->sole()->update(['status' => 'archived']);
    $this->actingAs($user)->post('/crm/companies', companyInput());

    expect(Company::query()->orderBy('number')->pluck('number')->all())->toBe(['C-00001', 'C-00002']);
    $this->get('/crm/companies')->assertInertia(fn (AssertableInertia $page) => $page->has('companies.data', 1)->where('companies.data.0.number', 'C-00002'));
    $this->get('/crm/companies?status=archived')->assertInertia(fn (AssertableInertia $page) => $page->where('companies.data.0.number', 'C-00001'));
    $this->get('/crm/companies?search=LEARN')->assertInertia(fn (AssertableInertia $page) => $page->has('companies.data', 1));
    $this->get('/crm/companies?search=%25')->assertInertia(fn (AssertableInertia $page) => $page->has('companies.data', 0));
});

it('saves nothing when one part is wrong', function (array $changes, string $field) {
    $this->actingAs(crmUser('controller'))->post('/crm/companies', companyInput($changes))->assertSessionHasErrors($field);
    expect(Company::query()->count())->toBe(0);
})->with([
    'VAT ID of another country' => [['tax' => ['tax_status' => 'eu_business', 'country_code' => 'DE', 'vat_id' => 'ATU12345678']], 'tax.vat_id'],
    'missing VAT ID' => [['tax' => ['tax_status' => 'eu_business', 'country_code' => 'DE']], 'tax.vat_id'],
    'domestic abroad' => [['tax' => ['tax_status' => 'domestic', 'country_code' => 'DE']], 'tax.tax_status'],
    'EU business in Serbia' => [['tax' => ['tax_status' => 'eu_business', 'country_code' => 'RS', 'vat_id' => 'X1234']], 'tax.tax_status'],
    'Leitweg-ID' => [['leitweg_id' => '04011000-1234512345-07'], 'leitweg_id'],
    'address without city' => [['address' => ['line1' => 'x', 'country_code' => 'DE']], 'address.city'],
    'same contact address twice' => [['contacts' => [['last_name' => 'A', 'email' => 'a@example.test'], ['last_name' => 'B', 'email' => 'A@example.test']]], 'contacts.0.email'],
    'unknown language' => [['document_locale' => 'zz'], 'document_locale'],
    'unknown key' => [['tax' => ['tax_status' => 'private', 'country_code' => 'BA', 'tenant_id' => 'x']], 'tax'],
]);

it('keeps the history of the tax profile', function () {
    $user = crmUser('controller');
    $this->travelTo('2026-11-24 10:00:00');
    $this->actingAs($user)->post('/crm/companies', companyInput());
    $id = Company::query()->sole()->id;

    $private = ['tax' => ['tax_status' => 'private', 'country_code' => 'DE']];
    $this->travelTo('2026-12-01 00:30:00');
    $save = fn () => app(Companies::class)->update(Company::query()->findOrFail($id), ['company' => [], ...$private], $user);
    $save();
    // Unchanged data adds no second entry.
    $save();

    $profiles = CompanyTaxProfile::query()->orderBy('id')->get();
    expect($profiles)->toHaveCount(2)
        ->and($profiles[0]->valid_from)->toBeNull()
        ->and($profiles[0]->valid_to->toDateString())->toBe('2026-11-30')
        ->and($profiles[1]->valid_from->toDateString())->toBe('2026-12-01')
        ->and($profiles[1]->valid_to)->toBeNull();
});

it('replaces contacts, lets them swap addresses and keeps parts that are left out', function () {
    $user = crmUser('controller');
    $this->actingAs($user)->post('/crm/companies', companyInput(['contacts' => [['last_name' => 'A', 'email' => 'a@example.test'], ['last_name' => 'B', 'email' => 'b@example.test']]]));
    $id = Company::query()->sole()->id;
    [$a, $b] = Contact::query()->orderBy('last_name')->get()->all();

    $swapped = companyInput(['contacts' => [['id' => $a->id, 'last_name' => 'A', 'email' => 'b@example.test'], ['id' => $b->id, 'last_name' => 'B', 'email' => 'a@example.test']]]);
    $this->put("/crm/companies/{$id}", $swapped)->assertSessionHasNoErrors();
    expect($a->refresh()->email_normalized)->toBe('b@example.test');

    $base = companyInput();
    unset($base['contacts'], $base['sites'], $base['tax'], $base['address']);
    $this->put("/crm/companies/{$id}", [...$base, 'name' => 'Renamed'])->assertSessionHasNoErrors();
    expect(Contact::query()->count())->toBe(2)->and(Company::query()->sole()->name)->toBe('Renamed');

    $this->put("/crm/companies/{$id}", [...$base, 'contacts' => [], 'sites' => []])->assertSessionHasNoErrors();
    expect(Contact::query()->count())->toBe(0)->and(Address::query()->count())->toBe(1);
});

it('follows the permissions', function () {
    $this->get('/crm/companies')->assertRedirect(route('login'));
    $this->actingAs(crmUser('employee'))->get('/crm/companies')->assertForbidden();

    $lead = crmUser('project_lead');
    $this->actingAs($lead)->get('/crm/companies')->assertOk();
    $this->actingAs($lead)->post('/crm/companies', companyInput())->assertForbidden();

    $this->actingAs(crmUser('controller'))->post('/crm/companies', companyInput());
    $id = Company::query()->sole()->id;

    // Manage without the rights for contacts and tax data.
    $role = Role::query()->where('key', 'employee')->firstOrFail();
    app(RoleManager::class)->grant($role, ['crm.view', 'crm.manage']);
    $clerk = crmUser('employee');
    $base = companyInput();
    unset($base['contacts'], $base['tax']);
    $this->actingAs($clerk)->post('/crm/companies', $base)->assertForbidden();
    $this->actingAs($clerk)->put("/crm/companies/{$id}", [...$base, 'contacts' => []])->assertForbidden();
    $this->actingAs($clerk)->put("/crm/companies/{$id}", [...$base, 'tax' => companyInput()['tax']])->assertForbidden();
    $this->actingAs($clerk)->put("/crm/companies/{$id}", $base)->assertSessionHasNoErrors();
});

it('is closed while the module is switched off', function () {
    $user = crmUser('controller');
    app(TenantModules::class)->disable('crm');
    app()->forgetScopedInstances();

    $status = $this->actingAs($user)->get('/crm/companies')->status();
    expect($status)->toBeIn([403, 404]);
});

it('never reaches another tenant', function () {
    $this->actingAs(crmUser('controller'))->post('/crm/companies', companyInput());
    $mine = Company::query()->sole();

    $other = Tenant::factory()->create();
    $foreign = app(TenantContext::class)->run($other, fn () => Company::query()->create([
        ...$mine->only(['number', 'name', 'kind', 'status', 'document_locale', 'currency', 'payment_days', 'einvoice_format']),
    ]));
    $foreignContact = app(TenantContext::class)->run($other, fn () => $foreign->contacts()->create(['last_name' => 'Foreign']));

    $this->get("/crm/companies/{$foreign->id}")->assertNotFound();
    $this->put("/crm/companies/{$foreign->id}", companyInput())->assertNotFound();
    $this->get('/crm/companies')->assertInertia(fn (AssertableInertia $page) => $page->has('companies.data', 1));
    $this->put("/crm/companies/{$mine->id}", companyInput(['contacts' => [['id' => $foreignContact->id, 'last_name' => 'Taken']]]))->assertNotFound();
    expect(Contact::query()->count())->toBe(1);

    expect(fn () => Contact::query()->create(['company_id' => $foreign->id, 'last_name' => 'x']))->toThrow(QueryException::class);
});

it('ships its texts', function () {
    expect(__('crm:errors.leitweg_id'))->not->toContain('crm:')->and(app(StringCatalog::class)->message('bs', 'crm:title'))->toBe('Klijenti');
});

it('knows the formats of VAT IDs', function (string $country, string $value, bool $valid) {
    expect(VatId::isValid(VatId::normalize($value), $country))->toBe($valid);
})->with([
    ['DE', 'DE 123456789', true], ['DE', 'DE12345678', false], ['AT', 'ATU12345678', true], ['GR', 'EL123456789', true], ['GR', 'GR123456789', false],
    ['NL', 'NL123456789B01', true], ['BA', '123456789012', true], ['BA', 'BA123', false], ['US', '12-3456789', true], ['DE', "DE123456789\n1", false],
]);

it('checks the Leitweg-ID', function (string $value, bool $valid) {
    expect(LeitwegId::isValid($value))->toBe($valid);
})->with([['04011000-1234512345-06', true], ['04011000-1234512345-07', false], ['abc', false], ['04011000-1234512345', false]]);

it('serves the API with the same rules', function () {
    $user = crmUser('admin');
    app(RoleManager::class)->grant(Role::query()->where('key', 'admin')->firstOrFail(), ['core.api_tokens.manage']);
    $token = fn (array $scopes): string => app(ApiTokens::class)->create($user, 't', $scopes, 30)['secret'];

    $created = $this->withToken($token(['crm:read', 'crm:write']))->postJson('/api/v1/crm/companies', companyInput())->assertCreated()->json();
    expect($created['contacts'][0]['email'])->toBe('erika@example.test');
    $this->getJson("/api/v1/crm/companies/{$created['id']}")->assertOk()->assertJsonPath('tax.vat_id', 'DE999999999');
    $this->getJson('/api/v1/crm/companies')->assertOk()->assertJsonPath('data.0.number', 'C-00001');
    $this->putJson("/api/v1/crm/companies/{$created['id']}", companyInput(['name' => 'API']))->assertOk()->assertJsonPath('name', 'API');
    $this->postJson('/api/v1/crm/companies', [])->assertUnprocessable();
});

it('needs the write scope to write', function () {
    $user = crmUser('admin');
    $secret = app(ApiTokens::class)->create($user, 't', ['crm:read'], 30)['secret'];
    $this->withToken($secret)->postJson('/api/v1/crm/companies', companyInput())->assertForbidden();
});

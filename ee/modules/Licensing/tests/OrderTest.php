<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

use App\Authorization\RoleManager;
use App\I18n\Strings;
use App\Licensing\LicenseKeys;
use App\Licensing\LicenseToken;
use App\Models\AppNotification;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\EditionNotLicensed;
use App\Modules\ModuleRegistry;
use App\Modules\TenantModules;
use App\Settings\Settings;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Modules\Crm\Domain\Companies;
use Modules\Invoices\Contracts\InvoicePaid;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Invoices;
use Modules\Licensing\Domain\LicenseOrder;
use Modules\Licensing\Domain\LicenseOrderEvent;
use Modules\Licensing\Domain\LicensePlan;
use Modules\Licensing\Domain\Orders;
use Psr\Log\NullLogger;

/*
 * S-0973 plans, S-0975 direct sale.
 */

beforeEach(function () {
    $this->travelTo('2026-12-01 09:00:00');
    $this->tenant->update(['country_code' => 'BA', 'base_currency' => 'BAM', 'timezone' => 'Europe/Sarajevo']);
    app(TenantModules::class)->enable('licensing');
    $this->admin = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($this->admin, Role::query()->where('key', 'admin')->firstOrFail());
    app(Settings::class)->save('invoices', ['legal_name' => 'Synthetic Licensor', 'street' => 'Testna 1', 'postal_code' => '72270', 'city' => 'Travnik', 'tax_number' => '4000000000000', 'vat_id' => '123456789012', 'vat_status' => 'registered', 'bank' => null, 'iban' => null, 'bic' => null, 'contact' => null, 'contact_name' => null, 'contact_phone' => null, 'contact_email' => null, 'legal_texts_confirmed' => true], $this->admin);
    app()->forgetScopedInstances();
    $this->company = app(Companies::class)->create(['company' => ['name' => 'Beispiel GmbH', 'kind' => 'customer', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'pdf'], 'tax' => ['tax_status' => 'eu_business', 'country_code' => 'DE', 'vat_id' => 'DE123456789'], 'address' => ['line1' => 'Weg 1', 'postal_code' => '70173', 'city' => 'Stuttgart', 'country_code' => 'DE']], $this->admin);
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);
});

function sell(): LicenseOrder
{
    test()->post('/licensing/plans', ['key' => 'founding_customer', 'name' => 'Founding Customer', 'edition' => 'business', 'term_months' => 12, 'price_lock_months' => 24, 'full_price' => '240', 'light_price' => '96,50', 'onboarding_price' => '1000', 'currency' => 'EUR'])->assertSessionHasNoErrors();
    test()->post('/licensing/orders', ['plan_id' => LicensePlan::query()->sole()->id, 'company_id' => test()->company->id, 'full_seats' => 10, 'light_seats' => 5, 'instance_id' => 'inst_01JD8Q7Z5M3K9V2X4B6N8P0R1T', 'domain' => 'projects.customer.test', 'customer_name' => 'Beispiel GmbH', 'starts_on' => '2026-12-01'])->assertSessionHasNoErrors();

    return LicenseOrder::query()->sole();
}

it('turns an order into an invoice draft, a license to sign and an active license on payment', function () {
    $order = sell();
    $invoice = Invoice::query()->sole();
    // 10 x 240 + 5 x 96.50 + 1000, no BiH VAT for a German business.
    expect([$order->number, $order->status, $invoice->status, $invoice->net_minor])->toBe(['LO-2026-0001', 'pending_payment', 'draft', 388250]);
    app(Invoices::class)->finalize($invoice, $this->admin);
    expect($invoice->refresh()->tax_minor)->toBe(0)->and($invoice->content()['lines'][0]['tax_case'])->toBe('BA_EXPORT_SERVICE_B2B');

    $pair = sodium_crypto_sign_keypair();
    app()->instance(LicenseKeys::class, new LicenseKeys([['kid' => 'test', 'public_key' => base64_encode(sodium_crypto_sign_publickey($pair)), 'valid_from' => '2000-01-01T00:00:00Z', 'valid_until' => '2100-01-01T00:00:00Z', 'status' => 'active']]));
    $sign = fn (array $changes = []): string => LicenseToken::encode([...$this->get("/licensing/orders/{$order->id}/payload")->assertOk()->json(), 'kid' => 'test', 'issued_at' => '2026-12-01T09:00:00Z', ...$changes], sodium_crypto_sign_secretkey($pair));
    $enter = fn (string $token): TestResponse => $this->post("/licensing/orders/{$order->id}/license", ['token' => $token]);

    $enter('PTKL1.x.y')->assertSessionHasErrors('token');
    $enter($sign(['seats' => ['full' => 99, 'light' => 5]]))->assertSessionHasErrors('token');
    $enter($sign())->assertSessionHasNoErrors();
    $pending = LicenseToken::decode((string) $order->refresh()->token, app(LicenseKeys::class));
    expect($pending->declaredStatus)->toBe('pending_payment')->and($pending->expiresAt->toDateString())->toBe('2027-12-01');

    app(Invoices::class)->pay($invoice, '2026-12-01', $invoice->gross_minor, null, $this->admin);
    expect([$order->refresh()->status, $order->serial])->toBe(['paid', 2])->and(AppNotification::query()->where('type', 'licensing.order')->count())->toBe(1);
    app(Orders::class)->paid($invoice->id);
    expect($order->refresh()->serial)->toBe(2);
    $enter((string) $order->token)->assertSessionHasErrors('token');
    $enter($sign())->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe('active')->and(LicenseToken::decode((string) $order->token, app(LicenseKeys::class))->declaredStatus)->toBe('active')
        ->and(LicenseOrderEvent::query()->orderBy('id')->pluck('event')->sort()->values()->all())->toBe(['activated', 'confirmed', 'downloaded', 'downloaded', 'downloaded', 'issued', 'paid'])
        ->and(fn () => LicenseOrderEvent::query()->firstOrFail()->delete())->toThrow(LogicException::class);
    $this->get("/licensing/orders/{$order->id}")->assertOk();

    // Money given back: nothing more is signed until somebody looked.
    app(Invoices::class)->pay($invoice->refresh(), '2026-12-01', -100, 'returned', $this->admin);
    $this->get("/licensing/orders/{$order->id}/payload")->assertRedirect();
    $this->artisan('projektrack:licensing:check');
    expect($order->refresh()->status)->toBe('review');
});

it('refuses a license that was changed before it was signed', function () {
    $order = sell();
    $pair = sodium_crypto_sign_keypair();
    app()->instance(LicenseKeys::class, new LicenseKeys([['kid' => 'test', 'public_key' => base64_encode(sodium_crypto_sign_publickey($pair)), 'valid_from' => '2000-01-01T00:00:00Z', 'valid_until' => '2100-01-01T00:00:00Z', 'status' => 'active']]));
    $payload = [...$this->get("/licensing/orders/{$order->id}/payload")->json(), 'kid' => 'test', 'issued_at' => '2026-12-01T09:00:00Z'];
    foreach ([['status_until' => '2027-12-01T00:00:00Z'], ['addons' => ['white_label']], ['grace' => [...$payload['grace'], 'expiry_days' => 90]], ['plan' => 'x'], ['binding' => [...$payload['binding'], 'domains' => ['other.test']]]] as $change) {
        $this->post("/licensing/orders/{$order->id}/license", ['token' => LicenseToken::encode([...$payload, ...$change], sodium_crypto_sign_secretkey($pair))])->assertSessionHasErrors('token');
    }
    $first = LicenseToken::encode($payload, sodium_crypto_sign_secretkey($pair));
    $this->post("/licensing/orders/{$order->id}/license", ['token' => $first])->assertSessionHasNoErrors();
    $this->post("/licensing/orders/{$order->id}/license", ['token' => LicenseToken::encode([...$payload, 'issued_at' => '2026-12-01T10:00:00Z'], sodium_crypto_sign_secretkey($pair))])->assertSessionHasErrors('token');
});

it('asks a person when the invoice is not the agreed one', function () {
    $order = sell();
    $invoice = Invoice::query()->sole();
    app(Invoices::class)->change($invoice, '2026-12-01', '2026-12-01', [['description' => 'x', 'quantity' => '1', 'unit' => 'piece', 'unit_price' => '1', 'rate_class' => 'standard']], $this->admin);
    app(Invoices::class)->finalize($invoice->refresh(), $this->admin);
    app(Invoices::class)->pay($invoice->refresh(), '2026-12-01', $invoice->gross_minor, null, $this->admin);
    expect([$order->refresh()->status, $order->serial])->toBe(['review', 1]);
});

it('notices a payment nobody told about', function () {
    $order = sell();
    app(Invoices::class)->finalize(Invoice::query()->sole(), $this->admin);
    Event::forget(InvoicePaid::class);
    app(Invoices::class)->pay($i = Invoice::query()->sole(), '2026-12-01', $i->gross_minor, null, $this->admin);
    expect($order->refresh()->status)->toBe('pending_payment');
    $this->artisan('projektrack:licensing:check');
    expect($order->refresh()->status)->toBe('paid');
});

it('keeps order and invoice together when one is taken back', function () {
    $order = sell();
    $draft = Invoice::query()->sole();
    $this->delete("/invoices/{$draft->id}")->assertSessionHasErrors('invoice');
    app(Invoices::class)->finalize($draft, $this->admin);
    $this->post("/licensing/orders/{$order->id}/cancel")->assertSessionHasErrors('order');
    $this->post("/licensing/orders/{$order->id}/invoice")->assertSessionHasErrors('order');
    app(Invoices::class)->cancel($draft->refresh(), $this->admin);
    LicensePlan::query()->sole()->update(['full_price' => '999']);
    $this->post("/licensing/orders/{$order->id}/invoice")->assertSessionHasNoErrors();
    $again = Invoice::query()->findOrFail($order->refresh()->invoice_id);
    expect($again->id)->not->toBe($draft->id)->and($again->net_minor)->toBe(388250);
    $this->post("/licensing/orders/{$order->id}/cancel")->assertSessionHasNoErrors();
    $this->delete("/invoices/{$again->id}")->assertSessionHasNoErrors();
    $this->get("/licensing/orders/{$order->id}/payload")->assertRedirect();
    expect($order->refresh()->status)->toBe('cancelled');
});

it('writes the lines in the language of the customer and locks the price', function () {
    $this->admin->update(['locale' => 'bs']);
    $order = sell();
    expect(Invoice::query()->sole()->lines()->get()[0]->description)->toContain('full users, ')->not->toContain('2026-12-01')
        ->and($order->price_locked_until->toDateString())->toBe('2028-11-30');
});

it('does not confirm what could never be invoiced, twice, or in the future', function () {
    $order = sell();
    $data = ['plan_id' => $order->license_plan_id, 'company_id' => $this->company->id, 'full_seats' => 1, 'light_seats' => 0, 'instance_id' => $order->instance_id, 'domain' => 'a.test', 'customer_name' => 'X', 'starts_on' => '2026-12-01'];
    $this->post('/licensing/orders', $data)->assertSessionHasErrors('instance_id');
    $this->post('/licensing/orders', [...$data, 'starts_on' => '2026-12-02'])->assertSessionHasErrors('starts_on');
    $private = app(Companies::class)->create(['company' => ['name' => 'Private', 'kind' => 'customer', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'pdf'], 'tax' => ['tax_status' => 'private', 'country_code' => 'DE'], 'address' => ['line1' => 'Weg 1', 'postal_code' => '70173', 'city' => 'Stuttgart', 'country_code' => 'DE']], $this->admin);
    $this->post('/licensing/orders', [...$data, 'instance_id' => 'inst_01JD8Q7Z5M3K9V2X4B6N8P0R1Z', 'company_id' => $private->id])->assertSessionHasErrors('company_id');
    expect(LicenseOrder::query()->count())->toBe(1)->and(Invoice::query()->count())->toBe(1);
});

it('ends a term on the right day', function (string $start, int $months, string $end) {
    expect(Orders::lastDay(CarbonImmutable::parse($start, 'UTC'), $months))->toBe($end);
})->with([['2026-12-01', 12, '2027-11-30'], ['2027-01-31', 1, '2027-02-28'], ['2028-02-29', 12, '2029-02-28'], ['2027-08-31', 6, '2028-02-29'], ['2026-11-30', 3, '2027-02-28']]);

it('shows no news and no plans of another company', function () {
    $order = sell();
    $other = Tenant::factory()->create();
    app(TenantContext::class)->run($other, fn () => expect(LicenseOrder::query()->count())->toBe(0)->and(LicensePlan::query()->count())->toBe(0)->and(fn () => app(TenantModules::class)->enable('licensing'))->toThrow(EditionNotLicensed::class));
});

it('tells the managers once when the grace ends unpaid', function () {
    sell();
    $this->artisan('projektrack:licensing:check');
    expect(AppNotification::query()->count())->toBe(0);
    $this->travel(16)->days();
    $this->artisan('projektrack:licensing:check');
    $this->artisan('projektrack:licensing:check');
    expect(AppNotification::query()->where('type', 'licensing.order')->count())->toBe(1)->and(LicenseOrder::query()->sole()->status)->toBe('pending_payment');
});

it('exists only on the instance of the licensor, for those who may', function () {
    $this->actingAs(tap(User::factory()->withTwoFactor()->create(), fn (User $u) => app(RoleManager::class)->assign($u, Role::query()->where('key', 'controller')->firstOrFail())))->get('/licensing')->assertForbidden();
    app(TenantModules::class)->disable('licensing');
    config(['licensing.vendor_instance' => false]);
    app()->forgetScopedInstances();
    expect(fn () => app(TenantModules::class)->enable('licensing'))->toThrow(EditionNotLicensed::class);
    $this->actingAs($this->admin)->get('/licensing')->assertNotFound();
});

it('keeps news out of the public strings and vendor modules off other instances', function () {
    expect(array_filter(array_keys(app(Strings::class)->bundle(['en'])['messages']), fn (string $id): bool => preg_match('/^core:about\.(new|limit)/', $id) === 1))->toBe([]);
    $paths = [base_path('modules'), base_path('ee/modules')];
    expect((new ModuleRegistry($paths, new NullLogger, false))->has('licensing'))->toBeFalse()
        ->and((new ModuleRegistry($paths, new NullLogger, true))->has('licensing'))->toBeTrue();
    $this->get('/about')->assertOk();
});

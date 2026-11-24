<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Models\Role;
use App\Models\User;
use App\Modules\TenantModules;
use App\Settings\Settings;
use Illuminate\Support\Facades\File;
use Modules\Crm\Domain\Companies;
use Modules\Invoices\Domain\InvoicePdf;
use Modules\Invoices\Domain\Invoices;

/*
 * Writes synthetic e-invoices (S-0968, S-0969) to the folder EINVOICE_SAMPLES,
 * where CI checks them with the official validators.
 */

it('writes samples for the official validators', function (string $name, string $format, string $seller, string $status, string $country, ?string $vatId, array $lines) {
    $folder = (string) getenv('EINVOICE_SAMPLES');
    File::ensureDirectoryExists($folder);
    $this->tenant->update(['country_code' => $seller === 'BA' ? 'BA' : 'DE', 'base_currency' => $seller === 'BA' ? 'BAM' : 'EUR', 'timezone' => 'Europe/Berlin']);
    app(TenantModules::class)->enable('invoices');
    $user = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($user, Role::query()->where('key', 'admin')->firstOrFail());
    app(Settings::class)->save('invoices', ['legal_name' => 'Synthetic Seller GmbH', 'street' => 'Teststr. 1', 'postal_code' => '70173', 'city' => 'Stuttgart', 'tax_number' => $seller === 'BA' ? null : '99/999/99999', 'vat_id' => ['DE' => 'DE999999999', 'small' => null, 'BA' => '123456789012'][$seller], 'vat_status' => $seller === 'small' ? 'small_business' : 'registered', 'bank' => 'Test Bank', 'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'contact' => 'billing@example.test', 'contact_name' => 'Test Contact', 'contact_phone' => '+49 711 0000000', 'contact_email' => 'billing@example.test', 'legal_texts_confirmed' => true], $user);
    app()->forgetScopedInstances();
    $company = app(Companies::class)->create([
        'company' => ['name' => "Customer {$country}", 'kind' => 'customer', 'document_locale' => 'de', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => $format, 'leitweg_id' => '04011000-1234512345-06'],
        'tax' => ['tax_status' => $status, 'country_code' => $country, 'vat_id' => $vatId],
        'address' => ['line1' => 'Weg 2', 'city' => 'Ort', 'postal_code' => '10000', 'country_code' => $country],
        'contacts' => [['last_name' => 'Amt', 'email' => 'invoice@example.test', 'invoice_recipient' => true]],
    ], $user);
    $today = now()->toDateString();
    $draft = fn () => app(Invoices::class)->draft($company->id, $today, $today, $lines, $user);
    $invoice = app(Invoices::class)->finalize($draft(), $user);
    $documents = ['invoice' => $invoice, 'credit' => app(Invoices::class)->credit($invoice->refresh(), [1 => 1000], 'Discount', $user), 'cancellation' => app(Invoices::class)->cancel(app(Invoices::class)->finalize($draft(), $user), $user)];
    foreach ($documents as $kind => $document) {
        $xml = $document->refresh()->content()['xml'] ?? null;
        expect($xml)->toBeString();
        File::put("{$folder}/{$name}-{$kind}.xml", $xml);
        if ($format === 'zugferd') {
            File::put("{$folder}/{$name}-{$kind}.pdf", app(InvoicePdf::class)->render($document));
        }
    }
})->with(function () {
    $line = fn (string $quantity, string $price, string $class = 'standard', string $unit = 'hour'): array => ['description' => 'Synthetic service', 'quantity' => $quantity, 'unit' => $unit, 'unit_price' => $price, 'rate_class' => $class];
    foreach (['zugferd', 'xrechnung_cii', 'xrechnung_ubl'] as $format) {
        yield [$format.'-domestic', $format, 'DE', 'domestic', 'DE', null, [$line('1.25', '99.99'), $line('3', '33.333')]];
        yield [$format.'-mixed', $format, 'DE', 'domestic', 'DE', null, [$line('2', '100'), $line('1', '50', 'reduced', 'piece'), $line('1', '-20', 'standard', 'flat')]];
        yield [$format.'-reverse', $format, 'DE', 'eu_business', 'AT', 'ATU12345678', [$line('10', '120')]];
        yield [$format.'-small', $format, 'small', 'domestic', 'DE', null, [$line('10', '120')]];
        yield [$format.'-ba', $format, 'BA', 'eu_business', 'DE', 'DE123456789', [$line('10', '120')]];
    }
})->skip(fn (): bool => (string) getenv('EINVOICE_SAMPLES') === '', 'only for the official validators (EINVOICE_SAMPLES)');

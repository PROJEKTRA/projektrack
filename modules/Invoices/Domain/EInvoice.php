<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\Domain\Money\Amount;
use Brick\Money\Currency;
use DOMDocument;
use DOMElement;
use Illuminate\Validation\ValidationException;

/**
 * The issued document as CII XML (EN 16931): embedded as ZUGFeRD/Factur-X, or
 * as XRechnung. Written from the sealed content with decimal strings, never
 * floats, and checked against the official schema. The business rules are
 * covered by tests; the official Schematron needs a validator outside PHP.
 */
final class EInvoice
{
    private const string RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    private const string RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const string UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private const array UNITS = ['piece' => 'C62', 'hour' => 'HUR', 'day' => 'DAY', 'flat' => 'LS'];

    private DOMDocument $xml;

    private string $currency = '';

    /**
     * What the format needs beyond a normal invoice.
     *
     * @param  array<string, string>  $seller
     * @param  array{einvoice_format: string, leitweg_id: string|null, email: string|null, address: array{postal_code: string|null, ...}|null, ...}  $buyer
     * @return list<string>
     */
    public static function missing(array $seller, array $buyer, string $currency): array
    {
        if ($buyer['einvoice_format'] !== 'pdf' && Currency::of($currency)->getDefaultFractionDigits() > 2) {
            return ['x_currency'];
        }
        if ($buyer['einvoice_format'] !== 'xrechnung_cii') {
            return [];
        }

        return array_keys(array_filter([
            'x_leitweg' => ($buyer['leitweg_id'] ?? '') === '',
            'x_contact' => $seller['contact_name'] === '' || $seller['contact_phone'] === '' || $seller['contact_email'] === '',
            'x_postal' => trim((string) ($buyer['address']['postal_code'] ?? '')) === '',
            'x_iban' => $seller['iban'] === '',
            'x_email' => ($buyer['email'] ?? '') === '',
        ]));
    }

    /** @param array<array-key, mixed> $c sealed content of the invoice */
    public function write(array $c): string
    {
        $buyer = self::map($c['buyer'] ?? null);
        $seller = self::map($c['seller'] ?? null);
        $xrechnung = self::text($buyer, 'einvoice_format') === 'xrechnung_cii';
        $this->currency = self::text($c, 'currency');
        $this->xml = new DOMDocument('1.0', 'UTF-8');
        $root = $this->xml->createElementNS(self::RSM, 'rsm:CrossIndustryInvoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', self::RAM);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', self::UDT);
        $this->xml->appendChild($root);
        $outside = in_array('O', array_map(fn (array $g): string => self::text($g, 'category'), self::rows($c['groups'] ?? null)), true);

        $context = $this->add($root, 'rsm:ExchangedDocumentContext');
        if ($xrechnung) {
            $this->add($this->add($context, 'ram:BusinessProcessSpecifiedDocumentContextParameter'), 'ram:ID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        }
        $this->add($this->add($context, 'ram:GuidelineSpecifiedDocumentContextParameter'), 'ram:ID', 'urn:cen.eu:en16931:2017'.($xrechnung ? '#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0' : ''));

        $document = $this->add($root, 'rsm:ExchangedDocument');
        $this->add($document, 'ram:ID', self::text($c, 'number'));
        $this->add($document, 'ram:TypeCode', self::text($c, 'kind') === 'invoice' ? '380' : '384');
        $this->date($this->add($document, 'ram:IssueDateTime'), self::text($c, 'issue_date'));
        foreach (array_filter(is_array($c['notices'] ?? null) ? $c['notices'] : [], is_string(...)) as $notice) {
            $this->add($this->add($document, 'ram:IncludedNote'), 'ram:Content', $notice);
        }

        $transaction = $this->add($root, 'rsm:SupplyChainTradeTransaction');
        foreach (self::rows($c['lines'] ?? null) as $line) {
            $this->line($transaction, $line);
        }

        $agreement = $this->add($transaction, 'ram:ApplicableHeaderTradeAgreement');
        if (self::text($buyer, 'leitweg_id') !== '') {
            $this->add($agreement, 'ram:BuyerReference', self::text($buyer, 'leitweg_id'));
        }
        $this->seller($this->add($agreement, 'ram:SellerTradeParty'), $seller, $outside);
        $this->buyer($this->add($agreement, 'ram:BuyerTradeParty'), $buyer, $outside);

        $delivery = $this->add($transaction, 'ram:ApplicableHeaderTradeDelivery');
        $this->date($this->add($this->add($delivery, 'ram:ActualDeliverySupplyChainEvent'), 'ram:OccurrenceDateTime'), self::text($c, 'service_to'));
        $this->settlement($this->add($transaction, 'ram:ApplicableHeaderTradeSettlement'), $c, $seller);

        $out = (string) $this->xml->saveXML();
        $problems = $this->validate($out);
        if ($problems !== []) {
            // The messages can quote the invoice, so they stay out of the log.
            throw ValidationException::withMessages(['invoice' => __('invoices:errors.einvoice')]);
        }

        return $out;
    }

    /** @return list<string> problems against the schema of EN 16931 */
    public function validate(string $xml): array
    {
        $document = new DOMDocument;
        $before = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET)) {
                return ['not XML'];
            }
            $document->schemaValidate(base_path('vendor/horstoeko/zugferd/src/schema/FACTUR-X_EN16931.xsd'));

            return array_values(array_map(fn (\LibXMLError $e): string => trim($e->message), libxml_get_errors()));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($before);
        }
    }

    /** @param array<array-key, mixed> $line */
    private function line(DOMElement $parent, array $line): void
    {
        $item = $this->add($parent, 'ram:IncludedSupplyChainTradeLineItem');
        $this->add($this->add($item, 'ram:AssociatedDocumentLineDocument'), 'ram:LineID', (string) self::int($line, 'position'));
        $this->add($this->add($item, 'ram:SpecifiedTradeProduct'), 'ram:Name', self::text($line, 'description'));

        // Time is billed in minutes at the price of 60, so nothing is rounded twice.
        $minutes = is_int($line['minutes'] ?? null) ? $line['minutes'] : null;
        $quantity = $minutes === null ? self::text($line, 'quantity') : (string) $minutes;
        $unit = $minutes === null ? (self::UNITS[self::text($line, 'unit')] ?? 'C62') : 'MIN';
        $price = self::text($line, 'unit_price');
        if (str_starts_with($price, '-')) {
            // A price is never negative (BR-27); the quantity is.
            $price = substr($price, 1);
            $quantity = str_starts_with($quantity, '-') ? substr($quantity, 1) : '-'.$quantity;
        }
        $net = $this->add($this->add($item, 'ram:SpecifiedLineTradeAgreement'), 'ram:NetPriceProductTradePrice');
        $this->add($net, 'ram:ChargeAmount', $price);
        if ($minutes !== null) {
            $this->add($net, 'ram:BasisQuantity', '60', ['unitCode' => $unit]);
        }
        $this->add($this->add($item, 'ram:SpecifiedLineTradeDelivery'), 'ram:BilledQuantity', $quantity, ['unitCode' => $unit]);

        $settlement = $this->add($item, 'ram:SpecifiedLineTradeSettlement');
        $tax = $this->add($settlement, 'ram:ApplicableTradeTax');
        $this->add($tax, 'ram:TypeCode', 'VAT');
        $this->add($tax, 'ram:CategoryCode', self::text($line, 'category'));
        if (self::text($line, 'category') !== 'O') {
            $this->add($tax, 'ram:RateApplicablePercent', self::percent(self::int($line, 'rate_ppm')));
        }
        $this->add($this->add($settlement, 'ram:SpecifiedTradeSettlementLineMonetarySummation'), 'ram:LineTotalAmount', $this->amount(self::int($line, 'net_minor')));
    }

    /** @param array<array-key, mixed> $seller */
    private function seller(DOMElement $party, array $seller, bool $outside): void
    {
        // An identifier other than the tax registration, so the seller is known without a VAT ID (BR-CO-26).
        $id = self::text($seller, 'tax_number') !== '' ? self::text($seller, 'tax_number') : self::text($seller, 'vat_id');
        $this->add($party, 'ram:ID', $id);
        $this->add($party, 'ram:Name', self::text($seller, 'name'));
        if (self::text($seller, 'contact_name') !== '') {
            $contact = $this->add($party, 'ram:DefinedTradeContact');
            $this->add($contact, 'ram:PersonName', self::text($seller, 'contact_name'));
            if (self::text($seller, 'contact_phone') !== '') {
                $this->add($this->add($contact, 'ram:TelephoneUniversalCommunication'), 'ram:CompleteNumber', self::text($seller, 'contact_phone'));
            }
            if (self::text($seller, 'contact_email') !== '') {
                $this->add($this->add($contact, 'ram:EmailURIUniversalCommunication'), 'ram:URIID', self::text($seller, 'contact_email'));
            }
        }
        $this->address($party, self::text($seller, 'postal_code'), self::text($seller, 'street'), '', self::text($seller, 'city'), self::text($seller, 'country'));
        if (self::text($seller, 'contact_email') !== '') {
            $this->add($this->add($party, 'ram:URIUniversalCommunication'), 'ram:URIID', self::text($seller, 'contact_email'), ['schemeID' => 'EM']);
        }
        $vatId = self::text($seller, 'vat_id');
        // A VAT ID starts with its country (BR-CO-09); a number without one, as in BiH, is a tax number.
        $isVatId = preg_match('/^[A-Z]{2}/', $vatId) === 1;
        if (self::text($seller, 'tax_number') !== '') {
            $this->add($this->add($party, 'ram:SpecifiedTaxRegistration'), 'ram:ID', self::text($seller, 'tax_number'), ['schemeID' => 'FC']);
        }
        if ($vatId !== '' && ! $isVatId && self::text($seller, 'tax_number') === '') {
            $this->add($this->add($party, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $vatId, ['schemeID' => 'FC']);
        }
        // Outside the scope of VAT there is no VAT ID on the document (BR-O-02).
        if ($isVatId && ! $outside) {
            $this->add($this->add($party, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $vatId, ['schemeID' => 'VA']);
        }
    }

    /** @param array<array-key, mixed> $buyer */
    private function buyer(DOMElement $party, array $buyer, bool $outside): void
    {
        $address = self::map($buyer['address'] ?? null);
        $tax = self::map($buyer['tax'] ?? null);
        $this->add($party, 'ram:ID', self::text($buyer, 'number'));
        $this->add($party, 'ram:Name', self::text($buyer, 'name'));
        $this->address($party, self::text($address, 'postal_code'), self::text($address, 'line1'), self::text($address, 'line2'), self::text($address, 'city'), self::text($address, 'country_code'));
        if (self::text($buyer, 'email') !== '') {
            $this->add($this->add($party, 'ram:URIUniversalCommunication'), 'ram:URIID', self::text($buyer, 'email'), ['schemeID' => 'EM']);
        }
        if (preg_match('/^[A-Z]{2}/', self::text($tax, 'vat_id')) === 1 && ! $outside) {
            $this->add($this->add($party, 'ram:SpecifiedTaxRegistration'), 'ram:ID', self::text($tax, 'vat_id'), ['schemeID' => 'VA']);
        }
    }

    /**
     * @param  array<array-key, mixed>  $c
     * @param  array<array-key, mixed>  $seller
     */
    private function settlement(DOMElement $s, array $c, array $seller): void
    {
        $this->add($s, 'ram:InvoiceCurrencyCode', $this->currency);
        if (self::text($seller, 'iban') !== '') {
            $means = $this->add($s, 'ram:SpecifiedTradeSettlementPaymentMeans');
            $this->add($means, 'ram:TypeCode', $this->currency === 'EUR' ? '58' : '30');
            $this->add($this->add($means, 'ram:PayeePartyCreditorFinancialAccount'), 'ram:IBANID', str_replace(' ', '', self::text($seller, 'iban')));
            if (self::text($seller, 'bic') !== '') {
                $this->add($this->add($means, 'ram:PayeeSpecifiedCreditorFinancialInstitution'), 'ram:BICID', self::text($seller, 'bic'));
            }
        }
        $reason = implode(' ', array_filter(is_array($c['notices'] ?? null) ? $c['notices'] : [], is_string(...)));
        foreach (self::rows($c['groups'] ?? null) as $group) {
            $category = self::text($group, 'category');
            $tax = $this->add($s, 'ram:ApplicableTradeTax');
            $this->add($tax, 'ram:CalculatedAmount', $this->amount(self::int($group, 'tax_minor')));
            $this->add($tax, 'ram:TypeCode', 'VAT');
            if ($category !== 'S' && $reason !== '') {
                $this->add($tax, 'ram:ExemptionReason', $reason);
            }
            $this->add($tax, 'ram:BasisAmount', $this->amount(self::int($group, 'net_minor')));
            $this->add($tax, 'ram:CategoryCode', $category);
            $code = ['AE' => 'VATEX-EU-AE', 'O' => 'VATEX-EU-O'][$category] ?? null;
            if ($code !== null) {
                $this->add($tax, 'ram:ExemptionReasonCode', $code);
            }
            if ($category !== 'O') {
                $this->add($tax, 'ram:RateApplicablePercent', self::percent(self::int($group, 'rate_ppm')));
            }
        }
        $period = $this->add($s, 'ram:BillingSpecifiedPeriod');
        $this->date($this->add($period, 'ram:StartDateTime'), self::text($c, 'service_from'));
        $this->date($this->add($period, 'ram:EndDateTime'), self::text($c, 'service_to'));
        $this->date($this->add($this->add($s, 'ram:SpecifiedTradePaymentTerms'), 'ram:DueDateDateTime'), self::text($c, 'due_date'));

        $sum = $this->add($s, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $this->add($sum, 'ram:LineTotalAmount', $this->amount(self::int($c, 'net_minor')));
        $this->add($sum, 'ram:TaxBasisTotalAmount', $this->amount(self::int($c, 'net_minor')));
        $this->add($sum, 'ram:TaxTotalAmount', $this->amount(self::int($c, 'tax_minor')), ['currencyID' => $this->currency]);
        $this->add($sum, 'ram:GrandTotalAmount', $this->amount(self::int($c, 'gross_minor')));
        $this->add($sum, 'ram:DuePayableAmount', $this->amount(self::int($c, 'gross_minor')));
        if (self::text($c, 'cancels') !== '') {
            $reference = $this->add($s, 'ram:InvoiceReferencedDocument');
            $this->add($reference, 'ram:IssuerAssignedID', self::text($c, 'cancels'));
        }
    }

    private function address(DOMElement $party, string $code, string $line1, string $line2, string $city, string $country): void
    {
        $address = $this->add($party, 'ram:PostalTradeAddress');
        foreach (['PostcodeCode' => $code, 'LineOne' => $line1, 'LineTwo' => $line2, 'CityName' => $city, 'CountryID' => $country] as $name => $value) {
            if ($value !== '') {
                $this->add($address, "ram:{$name}", $value);
            }
        }
    }

    private function date(DOMElement $parent, string $date): void
    {
        $this->add($parent, 'udt:DateTimeString', str_replace('-', '', $date), ['format' => '102']);
    }

    private function amount(int $minor): string
    {
        return Amount::decimal($minor, $this->currency);
    }

    private static function percent(int $ppm): string
    {
        return Amount::decimal(intdiv($ppm, 100), 'EUR');
    }

    /** @param array<string, string> $attributes */
    private function add(DOMElement $parent, string $name, ?string $value = null, array $attributes = []): DOMElement
    {
        $element = $this->xml->createElementNS(['rsm' => self::RSM, 'ram' => self::RAM, 'udt' => self::UDT][explode(':', $name)[0]] ?? self::RAM, $name);
        if ($value !== null) {
            // Characters XML does not know are dropped; everything else is escaped by the DOM.
            $element->appendChild($this->xml->createTextNode((string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value)));
        }
        foreach ($attributes as $key => $text) {
            $element->setAttribute($key, $text);
        }
        $parent->appendChild($element);

        return $element;
    }

    /** @return array<array-key, mixed> */
    private static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return list<array<array-key, mixed>> */
    private static function rows(mixed $value): array
    {
        return array_values(array_filter(self::map($value), is_array(...)));
    }

    /** @param array<array-key, mixed> $data */
    private static function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /** @param array<array-key, mixed> $data */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : 0;
    }
}

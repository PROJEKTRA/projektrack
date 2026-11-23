<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Domain\Money\Amount;
use App\Domain\Tax\TaxCase;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxEngine;
use App\Domain\Tax\TaxReviewRequired;
use App\Domain\Tax\TaxTotals;

/*
 * Money and tax (ADR-0004), with its test cases T1 to T12.
 */

function decision(string $seller = 'DE', string $status = 'registered', string $country = 'DE', string $customer = 'domestic', bool $vatId = true, string $class = 'standard', string $date = '2026-11-24')
{
    return (new TaxEngine)->decide(new TaxContext($seller, $status, $country, $customer, $vatId, $class, $date));
}

it('T1: rounds VAT once per rate', function () {
    $s = decision();
    $r = decision(class: 'reduced');
    $lines = [Amount::time(600, '100.00', 'EUR'), Amount::line('3', '33.33', 'EUR'), Amount::line('1', '49.99', 'EUR')];
    $t = TaxTotals::of([['decision' => $s, 'net_minor' => $lines[0]], ['decision' => $s, 'net_minor' => $lines[1]], ['decision' => $r, 'net_minor' => $lines[2]]]);

    expect($lines)->toBe([100000, 9999, 4999])
        ->and(array_column($t->groups, 'net_minor'))->toBe([109999, 4999])
        ->and(array_column($t->groups, 'tax_minor'))->toBe([20900, 350])
        ->and([$t->netMinor, $t->taxMinor, $t->grossMinor()])->toBe([114998, 21250, 136248]);
});

it('T2: rounds per group, not per line', function () {
    $s = decision();
    $t = TaxTotals::of(array_fill(0, 3, ['decision' => $s, 'net_minor' => 150]));
    expect([$t->taxMinor, $t->grossMinor()])->toBe([86, 536]);
});

it('T3: values minutes', function (int $minutes, string $rate, int $minor) {
    expect(Amount::time($minutes, $rate, 'EUR'))->toBe($minor);
})->with([[20, '100.00', 3333], [435, '80.00', 58000], [-60, '95.5000', -9550], [0, '100', 0], [1, '0.0001', 0]]);

it('T4: rounds half up, away from zero', function (string $amount, int $minor) {
    expect(Amount::line('1', $amount, 'EUR'))->toBe($minor);
})->with([['0.125', 13], ['-0.125', -13], ['0.1249', 12], ['0.005', 1], ['-0.005', -1]]);

it('knows currencies with other minor units', function () {
    expect(Amount::line('1', '10.5', 'JPY'))->toBe(11)
        ->and(Amount::line('1', '1.2345', 'KWD'))->toBe(1235)
        ->and(Amount::decimal(1235, 'KWD'))->toBe('1.235')
        ->and(Amount::decimal(-5, 'EUR'))->toBe('-0.05')
        ->and(Amount::isCurrency('EUR'))->toBeTrue()
        ->and(Amount::isCurrency('XXQ'))->toBeFalse();
});

it('T6: BA domestic', function () {
    $d = decision('BA', country: 'BA');
    $t = TaxTotals::of([['decision' => $d, 'net_minor' => 150000], ['decision' => $d, 'net_minor' => 33333]]);
    expect($d->case)->toBe(TaxCase::BaStandard)->and([$t->netMinor, $t->taxMinor, $t->grossMinor()])->toBe([183333, 31167, 214500]);
});

it('decides by the table', function (array $arguments, TaxCase $case, int $ppm, string $category) {
    $d = decision(...$arguments);
    expect([$d->case, $d->ratePpm, $d->category])->toBe([$case, $ppm, $category])->and($d->ruleRef)->toContain('@2026.1');
})->with([
    'T7 reverse charge' => [['country' => 'AT', 'customer' => 'eu_business'], TaxCase::EuB2bReverseCharge, 0, 'AE'],
    'T9 BA seller to DE business' => [['seller' => 'BA', 'country' => 'DE', 'customer' => 'eu_business'], TaxCase::BaExportService, 0, 'AE'],
    'BA seller to RS business' => [['seller' => 'BA', 'country' => 'RS', 'customer' => 'non_eu_business'], TaxCase::BaExportService, 0, 'O'],
    'BA seller to BA private' => [['seller' => 'BA', 'country' => 'BA', 'customer' => 'private'], TaxCase::BaStandard, 170000, 'S'],
    'BA not registered' => [['seller' => 'BA', 'status' => 'not_registered', 'country' => 'DE', 'customer' => 'eu_business'], TaxCase::BaNotRegistered, 0, 'O'],
    'T10 DE seller to US business' => [['country' => 'US', 'customer' => 'non_eu_business'], TaxCase::NonEuB2bOutsideScope, 0, 'O'],
    'CH business' => [['country' => 'CH', 'customer' => 'non_eu_business'], TaxCase::NonEuB2bOutsideScope, 0, 'O'],
    'T11 small business' => [['status' => 'small_business'], TaxCase::DeSmallBusiness, 0, 'E'],
    'small business, EU customer' => [['status' => 'small_business', 'country' => 'AT', 'customer' => 'eu_business', 'vatId' => false], TaxCase::DeSmallBusiness, 0, 'E'],
    'reduced' => [['class' => 'reduced'], TaxCase::DeReduced, 70000, 'S'],
    'exempt line' => [['class' => 'exempt'], TaxCase::Exempt, 0, 'E'],
    'EU private' => [['country' => 'FR', 'customer' => 'private'], TaxCase::EuB2cSellerCountry, 190000, 'S'],
    'public sector' => [['customer' => 'public_sector'], TaxCase::DeStandard, 190000, 'S'],
    'public sector in Austria with VAT ID' => [['country' => 'AT', 'customer' => 'public_sector'], TaxCase::EuB2bReverseCharge, 0, 'AE'],
]);

it('refuses instead of guessing', function (array $arguments, string $reason) {
    try {
        decision(...$arguments);
        $this->fail('decided');
    } catch (TaxReviewRequired $e) {
        expect($e->reason)->toBe($reason);
    }
})->with([
    'T8 no VAT ID' => [['country' => 'AT', 'customer' => 'eu_business', 'vatId' => false], 'vat_id_missing'],
    'seller in France' => [['seller' => 'FR', 'country' => 'FR'], 'seller_country'],
    'DE seller not registered' => [['status' => 'not_registered'], 'seller_status'],
    'BA reduced' => [['seller' => 'BA', 'country' => 'BA', 'class' => 'reduced'], 'rate_class'],
    'BA seller to private abroad' => [['seller' => 'BA', 'country' => 'DE', 'customer' => 'private'], 'private_abroad'],
    'DE seller to private outside EU' => [['country' => 'CH', 'customer' => 'private'], 'private_outside_eu'],
    'unknown customer status' => [['customer' => 'x'], 'customer_status'],
    'public sector in Switzerland' => [['country' => 'CH', 'customer' => 'public_sector'], 'customer_status'],
    'domestic in Switzerland' => [['country' => 'CH'], 'customer_status'],
    'BA: public sector in Serbia' => [['seller' => 'BA', 'country' => 'RS', 'customer' => 'public_sector'], 'customer_status'],
    'before the rate' => [['date' => '2020-12-31'], 'rate_class'],
]);

it('T12: converts with the stored rate', function () {
    expect(Amount::convert(51234, 'EUR', 'BAM', '1.95583'))->toBe(100205)
        ->and(Amount::convert(400000, 'EUR', 'BAM', '1.95583'))->toBe(782332)
        ->and(Amount::convert(1000, 'JPY', 'EUR', '0.0062'))->toBe(620);
});

it('negates exactly for cancellations', function () {
    $s = decision();
    $a = TaxTotals::of([['decision' => $s, 'net_minor' => 150]]);
    $b = TaxTotals::of([['decision' => $s, 'net_minor' => -150]]);
    expect($b->taxMinor)->toBe(-$a->taxMinor)->and($b->grossMinor())->toBe(-$a->grossMinor());
});

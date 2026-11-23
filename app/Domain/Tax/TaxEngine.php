<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Domain\Tax;

/**
 * Decision table of ADR-0004 section 6 for sellers in Germany and in Bosnia
 * and Herzegovina, general services. First match wins; without a match the
 * result is a refusal, never a guess.
 */
final class TaxEngine
{
    public const string VERSION = '2026.1';

    private const array EU = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

    /** country => rate class => [valid from, ppm], newest first */
    private const array RATES = [
        'DE' => ['standard' => [['2021-01-01', 190000]], 'reduced' => [['2021-01-01', 70000]]],
        'BA' => ['standard' => [['2006-01-01', 170000]]],
    ];

    public function decide(TaxContext $c): TaxDecision
    {
        $business = in_array($c->customerStatus, ['domestic', 'eu_business', 'non_eu_business', 'exempt', 'public_sector'], true);
        if (! $business && $c->customerStatus !== 'private') {
            throw new TaxReviewRequired('customer_status');
        }

        return match ($c->sellerCountry) {
            'DE' => $this->germany($c, $business),
            'BA' => $this->bosnia($c, $business),
            default => throw new TaxReviewRequired('seller_country'),
        };
    }

    private function germany(TaxContext $c, bool $business): TaxDecision
    {
        $eu = in_array($c->customerCountry, self::EU, true);

        return match (true) {
            $c->sellerStatus === 'small_business' => $this->free(TaxCase::DeSmallBusiness, 'E', 'DE.02'),
            $c->sellerStatus !== 'registered' => throw new TaxReviewRequired('seller_status'),
            $c->rateClass === 'exempt' => $this->free(TaxCase::Exempt, 'E', 'DE.03'),
            $c->customerCountry === 'DE' => $this->taxed($c, $c->rateClass === 'reduced' ? TaxCase::DeReduced : TaxCase::DeStandard, 'DE.04'),
            $eu && $business && $c->customerHasVatId => $this->free(TaxCase::EuB2bReverseCharge, 'AE', 'DE.05'),
            $eu && $business => throw new TaxReviewRequired('vat_id_missing'),
            $eu => $this->taxed($c, TaxCase::EuB2cSellerCountry, 'DE.07'),
            $business => $this->free(TaxCase::NonEuB2bOutsideScope, 'O', 'DE.08'),
            // Private persons outside the EU: the place of supply depends on the kind of service.
            default => throw new TaxReviewRequired('private_outside_eu'),
        };
    }

    private function bosnia(TaxContext $c, bool $business): TaxDecision
    {
        return match (true) {
            $c->sellerStatus === 'not_registered' => $this->free(TaxCase::BaNotRegistered, 'O', 'BA.11'),
            $c->sellerStatus !== 'registered' => throw new TaxReviewRequired('seller_status'),
            $c->rateClass !== 'standard' => throw new TaxReviewRequired('rate_class'),
            $c->customerCountry === 'BA' => $this->taxed($c, TaxCase::BaStandard, 'BA.12'),
            $business => $this->free(TaxCase::BaExportService, in_array($c->customerCountry, self::EU, true) ? 'AE' : 'O', 'BA.13'),
            // Needs the accountant (ADR-0004 row 14).
            default => throw new TaxReviewRequired('private_abroad'),
        };
    }

    private function free(TaxCase $case, string $category, string $rule): TaxDecision
    {
        return new TaxDecision($case, 0, $category, $rule.'@'.self::VERSION);
    }

    private function taxed(TaxContext $c, TaxCase $case, string $rule): TaxDecision
    {
        foreach (self::RATES[$c->sellerCountry][$c->rateClass] ?? [] as [$from, $ppm]) {
            if ($from <= $c->date) {
                return new TaxDecision($case, $ppm, 'S', $rule.'@'.self::VERSION);
            }
        }

        throw new TaxReviewRequired('rate_class');
    }
}

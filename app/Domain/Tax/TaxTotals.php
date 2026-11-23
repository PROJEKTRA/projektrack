<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Domain\Tax;

use App\Domain\Money\Amount;

/**
 * Totals of a document, VAT rounded once per rate and category (ADR-0004
 * section 2, EN 16931 rule BR-CO-17).
 */
final readonly class TaxTotals
{
    /** @param list<array{case: string, category: string, rate_ppm: int, net_minor: int, tax_minor: int}> $groups */
    private function __construct(
        public array $groups,
        public int $netMinor,
        public int $taxMinor,
    ) {}

    /** @param list<array{decision: TaxDecision, net_minor: int}> $lines */
    public static function of(array $lines): self
    {
        $groups = [];
        foreach ($lines as $line) {
            $d = $line['decision'];
            $key = sprintf('%07d|%s|%s', 1_000_000 - $d->ratePpm, $d->category, $d->case->value);
            $groups[$key] ??= ['case' => $d->case->value, 'category' => $d->category, 'rate_ppm' => $d->ratePpm, 'net_minor' => 0, 'tax_minor' => 0];
            $groups[$key]['net_minor'] += $line['net_minor'];
        }
        ksort($groups);
        $groups = array_map(fn (array $g): array => [...$g, 'tax_minor' => Amount::share($g['net_minor'], $g['rate_ppm'])], array_values($groups));

        return new self($groups, array_sum(array_column($groups, 'net_minor')), array_sum(array_column($groups, 'tax_minor')));
    }

    public function grossMinor(): int
    {
        return $this->netMinor + $this->taxMinor;
    }
}

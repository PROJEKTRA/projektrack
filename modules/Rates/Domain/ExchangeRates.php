<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Domain;

use Brick\Math\BigRational;
use Carbon\CarbonImmutable;

/**
 * The exchange rate of a day: the fixed one of BAM and EUR, else the newest
 * stored one up to that day, in either direction. None is never guessed.
 */
final class ExchangeRates
{
    /** Currency board of Bosnia and Herzegovina: 1 EUR in BAM. */
    public const string EUR_BAM = '1.95583';

    /** An older rate says nothing about the day; the value waits instead. */
    public const int MAX_AGE_DAYS = 31;

    public function on(string $from, string $to, string $date): ?BigRational
    {
        if ($from === $to) {
            return BigRational::one();
        }
        if ([$from, $to] === ['EUR', 'BAM']) {
            return BigRational::of(self::EUR_BAM);
        }
        if ([$from, $to] === ['BAM', 'EUR']) {
            return BigRational::one()->dividedBy(self::EUR_BAM);
        }
        $direct = $this->stored($from, $to, $date);
        $inverse = $this->stored($to, $from, $date);
        if ($direct !== null && ($inverse === null || $direct->rate_date >= $inverse->rate_date)) {
            return BigRational::of($direct->rate);
        }

        return $inverse === null ? null : BigRational::one()->dividedBy($inverse->rate);
    }

    private function stored(string $from, string $to, string $date): ?ExchangeRate
    {
        return ExchangeRate::query()->where('from_currency', $from)->where('to_currency', $to)->whereDate('rate_date', '<=', $date)
            ->whereDate('rate_date', '>=', CarbonImmutable::parse($date, 'UTC')->subDays(self::MAX_AGE_DAYS)->toDateString())->orderByDesc('rate_date')->first();
    }
}

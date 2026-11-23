<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Domain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\Money;

/**
 * The rounding points of ADR-0004: exact until here, then once, half up (away
 * from zero), to the minor units of the currency. Never float.
 */
final class Amount
{
    /** Quantity times unit price, in minor units. */
    public static function line(string $quantity, string $unitPrice, string $currency): int
    {
        return self::minor(BigRational::of($quantity)->multipliedBy(BigRational::of($unitPrice)), $currency);
    }

    /** Minutes at an hourly rate, in minor units. */
    public static function time(int $minutes, string $hourlyRate, string $currency): int
    {
        return self::minor(BigRational::of($hourlyRate)->multipliedBy($minutes)->dividedBy(60), $currency);
    }

    /** A share in parts per million of an amount in minor units. */
    public static function share(int $minor, int $ppm): int
    {
        return BigRational::of($minor)->multipliedBy($ppm)->dividedBy(1_000_000)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    /** Minor units converted with a stored exchange rate. */
    public static function convert(int $minor, string $from, string $to, string $rate): int
    {
        return self::minor(Money::ofMinor($minor, $from)->getAmount()->toBigRational()->multipliedBy(BigRational::of($rate)), $to);
    }

    /** As a plain decimal, for example "1234.50". */
    public static function decimal(int $minor, string $currency): string
    {
        return (string) Money::ofMinor($minor, $currency)->getAmount();
    }

    public static function isCurrency(string $code): bool
    {
        try {
            Currency::of($code);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function minor(BigRational $amount, string $currency): int
    {
        $scale = Currency::of($currency)->getDefaultFractionDigits();

        return $amount->multipliedBy(BigDecimal::ten()->power($scale))->toScale(0, RoundingMode::HalfUp)->toInt();
    }
}

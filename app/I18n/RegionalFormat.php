<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

use Brick\Money\Currency;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use InvalidArgumentException;
use NumberFormatter;

/**
 * Dates, times, numbers and amounts in one regional format and time zone
 * (S-0919, ADR-0005 section 3). Timestamps are stored in UTC and shown in
 * the time zone; calendar dates (work days, due dates) are not shifted.
 * resources/js/i18n/formats.ts gives the same output in the browser
 * (tests/fixtures/i18n/formats.json).
 */
final readonly class RegionalFormat
{
    /**
     * Amounts below 2^52 minor units divide into a float that rounds back to
     * the same digits (about 45 trillion in a currency with two decimals).
     */
    public const int EXACT_LIMIT = 2 ** 52;

    /**
     * @param  string|null  $datePattern  numeric date pattern replacing the CLDR one (config i18n.date_patterns)
     */
    public function __construct(
        public string $locale,
        public string $timezone,
        public ?string $datePattern = null,
    ) {}

    /** Day of a timestamp in the time zone: 01.11.2026 (bs), 11/01/2026 (en-US). */
    public function date(DateTimeInterface $at): string
    {
        return $this->dateFormatter($this->timezone)->format($at) ?: '';
    }

    /** A calendar date without a time (Y-m-d), never shifted by the time zone. */
    public function day(string $date): string
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if ($day === false) {
            throw new InvalidArgumentException("Not a date (Y-m-d): {$date}");
        }

        return $this->dateFormatter('UTC')->format($day) ?: '';
    }

    /** Time of a timestamp in the time zone: 15:05 (bs), 3:05 PM (en-US). */
    public function time(DateTimeInterface $at): string
    {
        return $this->pattern('jmm', $this->timezone)->format($at) ?: '';
    }

    /** Date and time of a timestamp in the time zone. */
    public function dateTime(DateTimeInterface $at): string
    {
        return $this->date($at).' '.$this->time($at);
    }

    /** A number with a fixed number of decimals: 1.234,50 (bs). */
    public function number(int|float $value, int $decimals = 0): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $formatter->format($value) ?: '';
    }

    /**
     * An amount in minor units with its ISO currency code: 1.234,50 EUR (bs),
     * EUR 1,234.50 (en-GB). Money stays an integer until this display step.
     */
    public function money(int $minor, string $currency): string
    {
        if (abs($minor) >= self::EXACT_LIMIT) {
            throw new InvalidArgumentException('Amount too large to display exactly.');
        }

        $digits = self::fractionDigits($currency);
        $formatter = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);
        $formatter->setPattern(str_replace('¤', '¤¤', (string) $formatter->getPattern()));
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);

        // Exact below EXACT_LIMIT: the float rounds back to the same digits.
        return $formatter->formatCurrency($minor / 10 ** $digits, strtoupper($currency)) ?: '';
    }

    /** Minor unit digits of a currency (EUR 2, JPY 0, KWD 3). */
    public static function fractionDigits(string $currency): int
    {
        // ISO 4217, as amounts are stored; ICU shows some currencies (RSD) without them.
        return Currency::of(strtoupper($currency))->getDefaultFractionDigits();
    }

    /**
     * Shape of the shared Inertia prop `formats`.
     *
     * @return array{locale: string, timeZone: string, datePattern: string|null}
     */
    public function toArray(): array
    {
        return ['locale' => $this->locale, 'timeZone' => $this->timezone, 'datePattern' => $this->datePattern];
    }

    private function dateFormatter(string $timezone): IntlDateFormatter
    {
        if ($this->datePattern !== null) {
            return new IntlDateFormatter($this->locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $timezone, IntlDateFormatter::GREGORIAN, $this->datePattern);
        }

        return $this->pattern('ddMMyyyy', $timezone);
    }

    private function pattern(string $skeleton, string $timezone): IntlDateFormatter
    {
        $pattern = IntlDatePatternGenerator::create($this->locale)?->getBestPattern($skeleton);

        return new IntlDateFormatter($this->locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $timezone, IntlDateFormatter::GREGORIAN, $pattern ?: null);
    }
}

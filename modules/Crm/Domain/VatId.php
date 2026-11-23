<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Domain;

/**
 * Format of a VAT ID per country (S-0939). Only the format: whether the number
 * is assigned is answered by VIES, which is not called.
 */
final class VatId
{
    /** VAT territory prefix => pattern after the prefix */
    private const array PATTERNS = [
        'AT' => 'U\d{8}', 'BE' => '[01]\d{9}', 'BG' => '\d{9,10}', 'CY' => '\d{8}[A-Z]', 'CZ' => '\d{8,10}', 'DE' => '\d{9}',
        'DK' => '\d{8}', 'EE' => '\d{9}', 'EL' => '\d{9}', 'ES' => '[A-Z0-9]\d{7}[A-Z0-9]', 'FI' => '\d{8}', 'FR' => '[A-Z0-9]{2}\d{9}',
        'HR' => '\d{11}', 'HU' => '\d{8}', 'IE' => '\d[A-Z0-9+*]\d{5}[A-Z]{1,2}', 'IT' => '\d{11}', 'LT' => '(\d{9}|\d{12})', 'LU' => '\d{8}',
        'LV' => '\d{11}', 'MT' => '\d{8}', 'NL' => '[A-Z0-9+*]{10}\d{2}', 'PL' => '\d{10}', 'PT' => '\d{9}', 'RO' => '\d{2,10}',
        'SE' => '\d{12}', 'SI' => '\d{8}', 'SK' => '\d{10}',
    ];

    /** @return list<string> ISO country codes of the EU */
    public static function euCountries(): array
    {
        return array_map(fn (string $prefix): string => $prefix === 'EL' ? 'GR' : $prefix, array_keys(self::PATTERNS));
    }

    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]+/', '', $value));
    }

    /** Countries without a known format accept letters and digits. */
    public static function isValid(string $normalized, string $country): bool
    {
        if ($country === 'BA') {
            return preg_match('/^\d{12}\z/', $normalized) === 1;
        }
        $prefix = $country === 'GR' ? 'EL' : $country;
        $pattern = self::PATTERNS[$prefix] ?? null;

        return preg_match($pattern === null ? '/^[A-Z0-9]{4,20}\z/' : "/^{$prefix}{$pattern}\z/", $normalized) === 1;
    }
}

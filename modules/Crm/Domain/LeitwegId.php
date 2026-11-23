<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Domain;

/**
 * Format and check digits (ISO 7064 MOD 97-10) of a Leitweg-ID.
 */
final class LeitwegId
{
    public static function isValid(string $value): bool
    {
        if (preg_match('/^\d{2,12}(-[A-Z0-9]{1,30})?-\d{2}\z/', $value) !== 1) {
            return false;
        }

        $remainder = 0;
        foreach (str_split(str_replace('-', '', $value)) as $char) {
            $number = ctype_digit($char) ? $char : (string) (ord($char) - 55);
            foreach (str_split($number) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}

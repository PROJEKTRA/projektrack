<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Branding;

/**
 * The licensor's legal entity (S-0911), stored once in config/licensor.php.
 * Used by the About screen and, with licensing, by license documents and
 * license invoices.
 */
final class Licensor
{
    /** Fields in display order. */
    public const array FIELDS = ['name', 'short_name', 'street', 'postal_code', 'city', 'country_code', 'jib', 'mbs', 'email', 'website'];

    /**
     * The known fields; empty ones are left out rather than shown blank.
     *
     * @return array<string, string>
     */
    public function details(): array
    {
        $details = [];

        foreach (self::FIELDS as $field) {
            $value = config("licensor.{$field}");

            if (is_string($value) && $value !== '') {
                $details[$field] = $value;
            }
        }

        return $details;
    }

    public function name(): string
    {
        return config()->string('licensor.name');
    }
}

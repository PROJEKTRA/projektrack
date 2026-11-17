<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Audit;

use InvalidArgumentException;

/**
 * Canonical JSON for hashing: keys sorted at every level, UTF-8, no escaped
 * slashes or Unicode, no insignificant whitespace. Floats are refused, because
 * their text form differs between engines; audit context holds identifiers,
 * integers (minor units) and strings.
 */
final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(self::normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_float($value)) {
            throw new InvalidArgumentException('Audit context must not contain floats; use integers or strings.');
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        ksort($value, SORT_STRING);

        $object = [];
        foreach ($value as $key => $item) {
            $object[(string) $key] = self::normalize($item);
        }

        return $object;
    }
}

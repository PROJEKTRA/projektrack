<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Support;

final class Lists
{
    /**
     * Scalar column values (keys, ids) from a query as a list of strings.
     *
     * @param  iterable<mixed>  $values
     * @return list<string>
     */
    public static function strings(iterable $values): array
    {
        $list = [];
        foreach ($values as $value) {
            if (is_scalar($value)) {
                $list[] = (string) $value;
            }
        }

        return $list;
    }
}

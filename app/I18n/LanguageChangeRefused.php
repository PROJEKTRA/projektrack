<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

use RuntimeException;

/**
 * A language change the tenant may not make (S-0921). Carries the string
 * identifiers that explain why, so the caller can show them in the user's
 * language. More than one reason is possible for an import.
 */
final class LanguageChangeRefused extends RuntimeException
{
    /**
     * @param  list<array{0: string, 1: array<string, string|int>}>  $reasons  identifier and arguments
     */
    public function __construct(public readonly string $field, public readonly array $reasons)
    {
        parent::__construct('Language change refused: '.implode(', ', array_column($reasons, 0)));
    }

    /**
     * @param  array<string, string|int>  $args
     */
    public static function because(string $field, string $identifier, array $args = []): self
    {
        return new self($field, [[$identifier, $args]]);
    }
}

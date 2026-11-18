<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses passwords that are easy to guess despite their length (S-0903,
 * security baseline section 2): entries of the bundled list, one repeated
 * character, and straight runs such as "123456789012" or "abcdefghijkl".
 * Works offline; the optional breach check is a separate, opt-in rule.
 */
final class NotCommonPassword implements ValidationRule
{
    /** @var array<string, true>|null */
    private static ?array $list = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (self::isCommon($value)) {
            $fail('core:auth.password_common')->translate();
        }
    }

    public static function isCommon(string $password): bool
    {
        $lower = mb_strtolower($password);

        return isset(self::list()[$lower]) || self::isRepeated($lower) || self::isRun($lower);
    }

    private static function isRepeated(string $password): bool
    {
        return preg_match('/^(.{1,8})\1+$/u', $password) === 1;
    }

    /** Every character one step after (or before) the previous one. */
    private static function isRun(string $password): bool
    {
        $codes = array_map('ord', str_split($password));
        $steps = [];

        for ($i = 1, $n = count($codes); $i < $n; $i++) {
            $steps[$codes[$i] - $codes[$i - 1]] = true;
        }

        return count($steps) === 1 && (isset($steps[1]) || isset($steps[-1]));
    }

    /** @return array<string, true> */
    private static function list(): array
    {
        if (self::$list !== null) {
            return self::$list;
        }

        $lines = file(resource_path('security/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        self::$list = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && ! str_starts_with($line, '#')) {
                self::$list[mb_strtolower($line)] = true;
            }
        }

        return self::$list;
    }
}

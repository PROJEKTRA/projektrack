<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

/**
 * Pseudo-locales (S-0917, ADR-0005 section 12): `xx` turns every en message
 * into accented, elongated text in brackets, so hard-coded text (plain),
 * truncation (the brackets are cut) and concatenation (several bracket pairs)
 * stand out. `xx-rtl` is the same text written right to left.
 *
 * Only literal text changes: arguments, plural and select syntax, `#`,
 * quoted ICU text and Laravel `:placeholders` stay as they are, so a pseudo
 * message formats with the same arguments as its source.
 */
final class Pseudo
{
    public const array LOCALES = ['xx', 'xx-rtl'];

    /** Plural rules and number formats of pseudo text follow en. */
    public const string FORMAT_LOCALE = 'en';

    private const array ACCENTS = [
        'a' => 'á', 'b' => 'ƀ', 'c' => 'ç', 'd' => 'ð', 'e' => 'é', 'f' => 'ƒ', 'g' => 'ĝ', 'h' => 'ĥ', 'i' => 'í',
        'j' => 'ĵ', 'k' => 'ķ', 'l' => 'ļ', 'm' => 'ɱ', 'n' => 'ñ', 'o' => 'ó', 'p' => 'þ', 'q' => 'ǫ', 'r' => 'ŕ',
        's' => 'š', 't' => 'ţ', 'u' => 'ú', 'v' => 'ṽ', 'w' => 'ŵ', 'x' => 'ẋ', 'y' => 'ý', 'z' => 'ž',
        'A' => 'Å', 'B' => 'Ɓ', 'C' => 'Ç', 'D' => 'Ð', 'E' => 'É', 'F' => 'Ƒ', 'G' => 'Ĝ', 'H' => 'Ĥ', 'I' => 'Í',
        'J' => 'Ĵ', 'K' => 'Ķ', 'L' => 'Ļ', 'M' => 'Ṁ', 'N' => 'Ñ', 'O' => 'Ó', 'P' => 'Þ', 'Q' => 'Ǫ', 'R' => 'Ŕ',
        'S' => 'Š', 'T' => 'Ţ', 'U' => 'Ú', 'V' => 'Ṽ', 'W' => 'Ŵ', 'X' => 'Ẋ', 'Y' => 'Ý', 'Z' => 'Ž',
    ];

    private const string VOWELS = 'aeiouAEIOU';

    public static function isPseudo(string $locale): bool
    {
        return in_array($locale, self::LOCALES, true);
    }

    public static function transform(string $message): string
    {
        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $i = 0;

        return '[!! '.self::text($chars, $i, false).' !!]';
    }

    /**
     * Literal text up to the closing brace of the enclosing sub-message.
     *
     * @param  list<string>  $chars
     */
    private static function text(array $chars, int &$i, bool $inPlural): string
    {
        $out = '';
        $count = count($chars);

        while ($i < $count) {
            $char = $chars[$i];

            if ($char === '}') {
                return $out;
            }

            if ($char === '{') {
                $out .= self::argument($chars, $i);

                continue;
            }

            if ($char === "'") {
                $out .= self::quoted($chars, $i);

                continue;
            }

            if ($char === '#' && $inPlural) {
                $out .= $char;
                $i++;

                continue;
            }

            // Laravel placeholders (:attribute) are filled in after formatting.
            if ($char === ':' && isset($chars[$i + 1]) && ctype_alpha($chars[$i + 1])) {
                $out .= $char;
                $i++;
                while ($i < $count && (ctype_alnum($chars[$i]) || $chars[$i] === '_')) {
                    $out .= $chars[$i++];
                }

                continue;
            }

            $out .= self::ACCENTS[$char] ?? $char;
            // Vowels twice: about 40 % longer, like many translations.
            if (str_contains(self::VOWELS, $char)) {
                $out .= self::ACCENTS[$char] ?? $char;
            }
            $i++;
        }

        return $out;
    }

    /**
     * One `{...}` argument, starting at its opening brace.
     *
     * @param  list<string>  $chars
     */
    private static function argument(array $chars, int &$i): string
    {
        $count = count($chars);
        $out = $chars[$i++];
        $header = '';

        // Name and type: everything up to the second comma or the closing brace.
        $commas = 0;
        while ($i < $count && $chars[$i] !== '}') {
            if ($chars[$i] === ',' && ++$commas === 2) {
                break;
            }
            $header .= $chars[$i++];
        }

        $out .= $header;
        $type = trim(explode(',', $header)[1] ?? '');

        if (! in_array($type, ['plural', 'select', 'selectordinal'], true)) {
            // Simple argument or a number/date/time style: copy verbatim.
            $depth = 0;
            while ($i < $count) {
                $char = $chars[$i++];
                $out .= $char;
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    if ($depth === 0) {
                        return $out;
                    }
                    $depth--;
                }
            }

            return $out;
        }

        // Options: selectors (one, =0, offset:1) each followed by {sub-message}.
        while ($i < $count && $chars[$i] !== '}') {
            if ($chars[$i] === '{') {
                $i++;
                $out .= '{'.self::text($chars, $i, $type !== 'select').'}';
                $i++;

                continue;
            }

            $out .= $chars[$i++];
        }

        if ($i < $count) {
            $out .= $chars[$i++];
        }

        return $out;
    }

    /**
     * ICU quoting: '' is an apostrophe; '{...}' is literal text.
     *
     * @param  list<string>  $chars
     */
    private static function quoted(array $chars, int &$i): string
    {
        $next = $chars[$i + 1] ?? '';

        if ($next === "'") {
            $i += 2;

            return "''";
        }

        if (! in_array($next, ['{', '}', '#', '|'], true)) {
            $i++;

            return "'";
        }

        $out = $chars[$i++];
        while ($i < count($chars)) {
            $out .= $chars[$i];
            if ($chars[$i++] === "'") {
                break;
            }
        }

        return $out;
    }
}

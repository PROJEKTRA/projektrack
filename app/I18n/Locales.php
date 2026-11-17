<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

/**
 * Which locales exist and in which order a string is looked up
 * (ADR-0005 section 5): user language, tenant language, the default (bs),
 * the source language (en). Regional variants fall back to their base
 * language first (es-US, then es).
 */
final class Locales
{
    /** @var list<string> */
    private readonly array $available;

    /** @var list<string> */
    private readonly array $rtl;

    /** @var list<string> */
    private readonly array $refused;

    /**
     * @param  list<string>  $available
     * @param  list<string>  $rtl
     * @param  list<string>  $refused
     */
    public function __construct(
        array $available,
        public readonly string $default,
        public readonly string $source,
        array $rtl = [],
        array $refused = [],
    ) {
        $this->refused = array_map(self::normalize(...), $refused);
        $this->rtl = array_map(self::normalize(...), $rtl);
        $this->available = array_values(array_unique(array_filter(
            array_map(self::normalize(...), [...$available, $default, $source]),
            fn (string $locale): bool => ! $this->isRefused($locale),
        )));
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return $this->available;
    }

    public function isAvailable(?string $locale): bool
    {
        return $locale !== null && in_array(self::normalize($locale), $this->available, true);
    }

    public function isRefused(string $locale): bool
    {
        return in_array(self::base(self::normalize($locale)), $this->refused, true);
    }

    public function direction(string $locale): string
    {
        return in_array(self::base(self::normalize($locale)), $this->rtl, true) ? 'rtl' : 'ltr';
    }

    /**
     * The lookup chain for a user and tenant language. Unknown or refused
     * locales are skipped, duplicates removed, and the chain always ends with
     * the default and the source language.
     *
     * @return non-empty-list<string>
     */
    public function chain(?string ...$preferred): array
    {
        $chain = [];

        foreach ([...$preferred, $this->default, $this->source] as $locale) {
            if ($locale === null || $locale === '') {
                continue;
            }

            $locale = self::normalize($locale);

            foreach (array_unique([$locale, self::base($locale)]) as $candidate) {
                if ($this->isAvailable($candidate) && ! in_array($candidate, $chain, true)) {
                    $chain[] = $candidate;
                }
            }
        }

        return $chain === [] ? [$this->source] : $chain;
    }

    /** "es_US" and "ES-us" become "es-US". */
    public static function normalize(string $locale): string
    {
        $parts = preg_split('/[-_]/', trim($locale)) ?: [];
        $language = strtolower($parts[0] ?? '');

        if (! isset($parts[1]) || $parts[1] === '') {
            return $language;
        }

        return $language.'-'.strtoupper($parts[1]);
    }

    public static function base(string $locale): string
    {
        return explode('-', $locale, 2)[0];
    }
}

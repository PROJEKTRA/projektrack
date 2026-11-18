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
 * language first (es-US, then es), a copied language to the language it was
 * copied from (S-0921).
 *
 * A locale is "known" when strings may be looked up in it and "available"
 * when users may choose it. A deactivated locale stays known: the default and
 * the source language remain the last links of every chain, and a copy still
 * falls back to its deactivated parent.
 */
final class Locales
{
    /** @var list<string> */
    private readonly array $known;

    /** @var list<string> */
    private readonly array $available;

    /** @var list<string> */
    private readonly array $rtl;

    /** @var list<string> */
    private readonly array $refused;

    /** @var array<string, string> locale => locale it was copied from */
    private readonly array $parents;

    /**
     * @param  list<string>  $available
     * @param  list<string>  $rtl
     * @param  list<string>  $refused
     * @param  bool  $pseudo  offer the pseudo-locales xx and xx-rtl (S-0917)
     * @param  list<string>  $disabled  known but not offered (deactivated by the tenant)
     * @param  array<string, string>  $parents  copied locale => locale it was copied from
     */
    public function __construct(
        array $available,
        public readonly string $default,
        public readonly string $source,
        array $rtl = [],
        array $refused = [],
        public readonly bool $pseudo = false,
        array $disabled = [],
        array $parents = [],
    ) {
        $this->refused = array_map(self::normalize(...), $refused);
        $this->rtl = array_values(array_unique(array_map(self::normalize(...), $rtl)));
        $this->known = array_values(array_unique(array_filter(
            array_map(self::normalize(...), [...$available, $default, $source, ...($pseudo ? Pseudo::LOCALES : [])]),
            fn (string $locale): bool => ! $this->isRefused($locale),
        )));

        $disabled = array_map(self::normalize(...), $disabled);
        $this->available = array_values(array_filter($this->known, fn (string $locale): bool => ! in_array($locale, $disabled, true)));

        $normalized = [];
        foreach ($parents as $locale => $parent) {
            $normalized[self::normalize($locale)] = self::normalize($parent);
        }
        $this->parents = $normalized;
    }

    /**
     * The same configuration seen by one tenant: its own (copied) languages
     * added, deactivated languages no longer offered.
     *
     * @param  array<string, array{direction: string, based_on: string|null}>  $custom  active copied languages
     * @param  list<string>  $disabled
     */
    public function forTenant(array $custom, array $disabled): self
    {
        $rtl = $this->rtl;
        $parents = [];

        foreach ($custom as $locale => $language) {
            if ($language['direction'] === 'rtl') {
                $rtl[] = $locale;
            }

            if ($language['based_on'] !== null) {
                $parents[$locale] = $language['based_on'];
            }
        }

        return new self(
            [...$this->known, ...array_keys($custom)],
            $this->default,
            $this->source,
            $rtl,
            $this->refused,
            false,
            $disabled,
            $parents,
        );
    }

    /**
     * Locales users may choose.
     *
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

    /**
     * Locales strings may be looked up in, offered or not.
     *
     * @return list<string>
     */
    public function known(): array
    {
        return $this->known;
    }

    public function isKnown(?string $locale): bool
    {
        return $locale !== null && in_array(self::normalize($locale), $this->known, true);
    }

    public function isRefused(string $locale): bool
    {
        return in_array(self::base(self::normalize($locale)), $this->refused, true);
    }

    public function parentOf(string $locale): ?string
    {
        return $this->parents[self::normalize($locale)] ?? null;
    }

    public function direction(string $locale): string
    {
        $locale = self::normalize($locale);

        return in_array($locale, $this->rtl, true) || in_array(self::base($locale), $this->rtl, true) ? 'rtl' : 'ltr';
    }

    /**
     * The lookup chain for a user and tenant language. Locales that are not
     * offered are skipped, duplicates removed, and the chain always ends with
     * the default and the source language.
     *
     * @return non-empty-list<string>
     */
    public function chain(?string ...$preferred): array
    {
        $chain = [];

        foreach ($preferred as $locale) {
            if ($locale === null || $locale === '') {
                continue;
            }

            $locale = self::normalize($locale);

            foreach (array_unique([$locale, self::base($locale)]) as $candidate) {
                if ($this->isAvailable($candidate)) {
                    $this->append($chain, $candidate);
                }
            }
        }

        foreach ([$this->default, $this->source] as $locale) {
            $locale = self::normalize($locale);

            if ($this->isKnown($locale)) {
                $this->append($chain, $locale);
            }
        }

        return $chain === [] ? [$this->source] : $chain;
    }

    /**
     * Add $locale and the languages it was copied from.
     *
     * @param  list<string>  $chain
     */
    private function append(array &$chain, string $locale): void
    {
        while (! in_array($locale, $chain, true) && $this->isKnown($locale)) {
            $chain[] = $locale;
            $parent = $this->parents[$locale] ?? null;

            if ($parent === null) {
                return;
            }

            $locale = $parent;
        }
    }

    /** "es_US" and "ES-us" become "es-US". */
    public static function normalize(string $locale): string
    {
        // Pseudo-locales keep their lower-case names (xx-rtl).
        if (Pseudo::isPseudo(strtolower(trim($locale)))) {
            return strtolower(trim($locale));
        }

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

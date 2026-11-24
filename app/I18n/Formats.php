<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

use App\Models\User;
use App\Settings\Settings;
use App\Tenancy\TenantContext;
use DateTimeImmutable;
use DateTimeZone;
use Locale;

/**
 * Picks the regional format and time zone (S-0919):
 * - format: the user's choice, else the organization's (setting
 *   core.format_locale), else the usual format of the language they see
 *   (config i18n.default_formats), else that language itself;
 * - time zone: the user's, else the tenant's, else UTC.
 */
final class Formats
{
    public function __construct(
        private readonly Strings $strings,
        private readonly TenantContext $tenants,
    ) {}

    /** Format of the signed-in user, or of the tenant before sign-in. */
    public function current(): RegionalFormat
    {
        $user = auth()->user();

        return $this->for($user instanceof User ? $user : null);
    }

    public function for(?User $user): RegionalFormat
    {
        $tenant = $this->tenants->has() ? $this->tenants->current() : null;
        $language = $this->strings->chain($user->locale ?? $tenant?->default_locale)[0];
        // The user's choice, else the organization's (Admin > Settings, S-0912),
        // else the usual format of the language.
        $chosen = $user->format_locale ?? ($tenant !== null ? app(Settings::class)->get('core.format_locale') : null);
        $locale = is_string($chosen) && self::isOffered($chosen) ? $chosen : self::defaultFor($language);

        $timezone = $user->timezone ?? $tenant->timezone ?? 'UTC';
        $timezone = self::isTimezone($timezone) ? $timezone : 'UTC';

        return new RegionalFormat($locale, $timezone, self::datePattern($locale));
    }

    /** The usual regional format of a language. */
    public static function defaultFor(string $language): string
    {
        $defaults = config()->array('i18n.default_formats');
        $default = $defaults[$language] ?? $defaults[Locale::getPrimaryLanguage($language)] ?? $language;

        return is_string($default) ? $default : 'en-GB';
    }

    /** @return list<string> */
    public static function offered(): array
    {
        return array_values(array_filter(config()->array('i18n.formats'), is_string(...)));
    }

    public static function isOffered(string $locale): bool
    {
        return in_array($locale, self::offered(), true);
    }

    public static function isTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Regional formats with their own name in the user's language and an
     * example, for the profile: "Deutsch (Deutschland): 01.11.2026, 1.234,50".
     *
     * @return list<array{value: string, label: string, example: string}>
     */
    public function options(string $language): array
    {
        $example = new DateTimeImmutable('2026-11-01 12:00:00', new DateTimeZone('UTC'));
        $options = [];

        foreach (self::offered() as $locale) {
            $format = new RegionalFormat($locale, 'UTC', self::datePattern($locale));
            $options[] = [
                'value' => $locale,
                'label' => (string) Locale::getDisplayName($locale, $language),
                'example' => $format->date($example).', '.$format->number(1234.5, 2),
            ];
        }

        usort($options, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * Time zones with their current offset: "Europe/Sarajevo (UTC+01:00)".
     *
     * @return list<array{value: string, label: string}>
     */
    public function timezones(): array
    {
        $now = new DateTimeImmutable('now');
        $options = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $offset = $now->setTimezone(new DateTimeZone($identifier))->format('P');
            $options[] = ['value' => $identifier, 'label' => "{$identifier} (UTC{$offset})"];
        }

        return $options;
    }

    /** Pattern override for the language of a format (config i18n.date_patterns). */
    public static function datePattern(string $locale): ?string
    {
        $pattern = config()->array('i18n.date_patterns')[Locale::getPrimaryLanguage($locale)] ?? null;

        return is_string($pattern) ? $pattern : null;
    }
}

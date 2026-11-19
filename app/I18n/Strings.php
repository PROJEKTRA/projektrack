<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

use App\Tenancy\TenantContext;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use IntlException;
use MessageFormatter;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

/**
 * The one translation layer for backend and frontend (S-0916, ADR-0005).
 *
 * An identifier `<component>:<key>` is looked up along the locale chain (user,
 * tenant, bs, en) and formatted as ICU MessageFormat with the plural rules of
 * the locale the message actually came from. A miss in a chain language is
 * logged once per identifier, locale and day. A raw identifier is never
 * returned: if a string is missing everywhere, the "text missing" message is.
 *
 * The tenant's own strings (copied languages, S-0921; overrides, S-0918) come
 * before the shipped ones in every chain language; installed language packs
 * (S-0921) slot in as a further layer in {@see resolve()}.
 */
final class Strings
{
    public const string MISSING = 'core:string_missing';

    /** Strings the browser never needs; they stay on the server. */
    private const array SERVER_ONLY = [
        'core:auth.failed',
        'core:auth.password',
        'core:auth.throttle',
        'core:email.',
        'core:pagination.',
        'core:passwords.',
        'core:validation.',
    ];

    private const string FSI = "\u{2068}";

    private const string PDI = "\u{2069}";

    /** @var array<string, MessageFormatter|false> */
    private array $formatters = [];

    /** @var array<string, true> */
    private array $reported = [];

    public function __construct(
        private readonly TenantLanguages $languages,
        private readonly StringCatalog $catalog,
        private readonly TenantContext $tenants,
        private readonly Application $app,
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
        /** @var Closure(): string the current brand name for {brand} (S-0911) */
        private readonly Closure $brand,
        /** @var Closure(): string|null {brand} in the public bundle: never the shipped product name */
        private readonly ?Closure $publicBrand = null,
    ) {}

    /**
     * The lookup chain for $locale (default: the current application locale)
     * followed by the tenant language, bs and en.
     *
     * @return non-empty-list<string>
     */
    public function chain(?string $locale = null): array
    {
        $tenantLocale = $this->tenants->has() ? $this->tenants->current()->default_locale : null;

        return $this->languages->locales()->chain($locale ?? $this->app->getLocale(), $tenantLocale);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function get(string $identifier, array $args = [], ?string $locale = null): string
    {
        $chain = $this->chain($locale);
        $resolved = $this->resolve($identifier, $chain);

        if ($resolved !== null) {
            $text = $this->format($identifier, $resolved[0], $args, $resolved[1]);
            if ($text !== null) {
                return $text;
            }
        }

        if ($identifier === self::MISSING) {
            return '';
        }

        $this->logger->error('String missing in every language', ['identifier' => $identifier, 'chain' => $chain]);

        return $this->get(self::MISSING, [], $locale);
    }

    /** True when the identifier exists in the source language. */
    public function has(string $identifier): bool
    {
        return $this->catalog->message($this->languages->locales()->source, $identifier) !== null;
    }

    /**
     * The message for $identifier and the locale it came from.
     *
     * @param  list<string>  $chain
     * @return array{0: string, 1: string}|null
     */
    public function resolve(string $identifier, array $chain, bool $logMisses = true): ?array
    {
        $tenant = $this->tenants->has();

        foreach ($chain as $locale) {
            $message = ($tenant ? ($this->languages->overrides($locale)[$identifier] ?? null) : null)
                ?? $this->catalog->message($locale, $identifier);

            if ($message !== null) {
                return [$message, $locale];
            }

            if ($logMisses) {
                $this->reportMiss($identifier, $locale);
            }
        }

        return null;
    }

    /**
     * Format one ICU message. Returns null when the message cannot be parsed
     * or formatted (the error is logged).
     *
     * @param  array<string, mixed>  $args
     */
    public function format(string $identifier, string $message, array $args, string $locale): ?string
    {
        $formatter = $this->formatter($identifier, $message, $locale);

        if ($formatter === null) {
            return null;
        }

        $rtl = $this->languages->locales()->direction($locale) === 'rtl';
        $values = ['brand' => ($this->brand)()];

        foreach ($args as $name => $value) {
            $value = match (true) {
                is_int($value), is_float($value) => $value,
                is_bool($value) => $value ? 1 : 0,
                $value instanceof DateTimeInterface => $value,
                is_string($value), $value instanceof Stringable => (string) $value,
                $value === null => '',
                default => null,
            };

            if ($value === null) {
                continue;
            }

            $values[(string) $name] = $rtl && is_string($value) ? self::FSI.$value.self::PDI : $value;
        }

        try {
            $text = $formatter->format($values);
        } catch (Throwable $e) {
            $text = false;
        }

        if ($text === false) {
            $this->logger->error('String could not be formatted', [
                'identifier' => $identifier,
                'locale' => $locale,
                'error' => $formatter->getErrorMessage(),
            ]);

            return null;
        }

        return $text;
    }

    /**
     * Every identifier the browser needs, resolved along $chain. Messages that
     * came from a later chain locale name it in `sources`, so the browser
     * applies that locale's plural rules.
     *
     * @param  non-empty-list<string>  $chain
     * @return array{locale: string, dir: string, version: string, messages: array<string, string>, sources: array<string, string>, reserved: array<string, string>}
     */
    public function bundle(array $chain): array
    {
        $messages = [];
        $sources = [];

        foreach (array_keys($this->catalog->messages($this->languages->locales()->source)) as $identifier) {
            if ($this->isServerOnly($identifier)) {
                continue;
            }

            $resolved = $this->resolve($identifier, $chain);
            if ($resolved === null) {
                continue;
            }

            $messages[$identifier] = $resolved[0];
            if ($resolved[1] !== $chain[0]) {
                $sources[$identifier] = $resolved[1];
            }
        }

        return [
            'locale' => $chain[0],
            'dir' => $this->languages->locales()->direction($chain[0]),
            'version' => $this->bundleVersion($chain),
            'messages' => $messages,
            'sources' => $sources,
            // The bundle is public; the browser sets the signed-in brand from the page (S-0911).
            'reserved' => ['brand' => ($this->publicBrand ?? $this->brand)()],
        ];
    }

    /**
     * @param  non-empty-list<string>  $chain
     */
    public function bundleVersion(array $chain): string
    {
        // The tenant is part of the version: two tenants' own strings never share one.
        $parts = [($this->publicBrand ?? $this->brand)(), $this->tenants->has() ? $this->tenants->id() : ''];
        foreach ($chain as $locale) {
            $parts[] = $locale.'='.$this->catalog->version($locale).':'.$this->languages->revision($locale);
        }

        return substr(hash('xxh128', implode("\0", $parts)), 0, 16);
    }

    public function direction(string $locale): string
    {
        return $this->languages->locales()->direction($locale);
    }

    /**
     * Unformatted messages of one locale.
     *
     * @return array<string, string>
     */
    public function catalogMessages(string $locale): array
    {
        return $this->catalog->messages($locale);
    }

    private function isServerOnly(string $identifier): bool
    {
        foreach (self::SERVER_ONLY as $prefix) {
            if (str_starts_with($identifier, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function formatter(string $identifier, string $message, string $locale): ?MessageFormatter
    {
        $key = $locale."\0".$message;

        if (! array_key_exists($key, $this->formatters)) {
            try {
                // The constructor throws on an invalid pattern (unlike create()).
                $formatLocale = Pseudo::isPseudo($locale) ? Pseudo::FORMAT_LOCALE : $locale;
                $this->formatters[$key] = new MessageFormatter(str_replace('-', '_', $formatLocale), $message);
            } catch (IntlException|\ValueError) {
                $this->formatters[$key] = false;
            }

            if ($this->formatters[$key] === false) {
                $this->logger->error('String is not valid ICU MessageFormat', ['identifier' => $identifier, 'locale' => $locale]);
            }
        }

        return $this->formatters[$key] ?: null;
    }

    /** Once per identifier, locale and day (ADR-0005 section 5). */
    private function reportMiss(string $identifier, string $locale): void
    {
        $key = 'strings.missing.'.$locale.'.'.$identifier.'.'.gmdate('Y-m-d');

        if (isset($this->reported[$key])) {
            return;
        }

        $this->reported[$key] = true;

        try {
            $first = $this->cache->add($key, true, 86400);
        } catch (Throwable) {
            $first = true;
        }

        if ($first) {
            $this->logger->warning('String missing in locale, fallback used', ['identifier' => $identifier, 'locale' => $locale]);
        }
    }
}

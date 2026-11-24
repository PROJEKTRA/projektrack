<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

use App\Audit\AuditLog;
use App\Authorization\Permissions;
use App\Models\StringOverride;
use App\Models\TenantLanguage;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use IntlException;
use Locale;
use MessageFormatter;
use Throwable;
use ValueError;

/**
 * The languages of the current tenant (S-0921, ADR-0005 sections 4 to 6).
 *
 * A tenant can deactivate a shipped language (its users then see the tenant
 * language; their own choice is kept for a later reactivation) and copy any
 * language into a new one of its own, which it then localizes: export as
 * JSON, translate, import. The strings of a copy are tenant overrides of the
 * new locale, marked "needs review" until a translation replaces them; a copy
 * falls back to the language it was copied from for strings added later.
 */
final class TenantLanguages
{
    public const string FORMAT = 'projektrack-strings';

    public const int MAX_MESSAGE_LENGTH = 5000;

    /** Upper bound for reasons listed when an import is refused. */
    private const int MAX_REASONS = 20;

    /** @var array<string, array<string, TenantLanguage>> tenant id => locale => row */
    private array $rows = [];

    /** @var array<string, Locales> */
    private array $views = [];

    /** @var array<string, array<string, string>> "tenant\0locale" => identifier => message */
    private array $overrides = [];

    public function __construct(
        private readonly Locales $base,
        private readonly StringCatalog $catalog,
        private readonly TenantContext $tenants,
        private readonly AuditLog $audit,
        private readonly Cache $cache,
    ) {}

    /** The locales as the current tenant sees them; the instance view without a tenant. */
    public function locales(): Locales
    {
        if (! $this->tenants->has()) {
            return $this->base;
        }

        $tenantId = $this->tenants->id();

        if (isset($this->views[$tenantId])) {
            return $this->views[$tenantId];
        }

        $custom = [];
        $disabled = [];

        foreach ($this->rows() as $locale => $row) {
            if (! $row->is_active) {
                $disabled[] = $locale;
            }

            if ($row->is_custom) {
                $custom[$locale] = ['direction' => $row->direction, 'based_on' => $row->based_on];
            }
        }

        return $this->views[$tenantId] = $this->base->forTenant($custom, $disabled);
    }

    /**
     * The tenant's own strings for $locale.
     *
     * @return array<string, string> identifier => ICU message
     */
    public function overrides(string $locale): array
    {
        $revision = $this->revision($locale);

        if ($revision === 0) {
            return [];
        }

        $tenantId = $this->tenants->id();
        $key = $tenantId."\0".$locale;

        if (isset($this->overrides[$key])) {
            return $this->overrides[$key];
        }

        $load = function () use ($locale): array {
            $messages = [];
            foreach (StringOverride::query()->where('scope', 'tenant')->where('locale', $locale)->get(['identifier', 'value']) as $override) {
                $messages[$override->identifier] = $override->value;
            }

            return $messages;
        };

        try {
            /** @var array<string, string> $messages */
            $messages = $this->cache->remember("strings.overrides.{$tenantId}.{$locale}.{$revision}", 86400, $load);
        } catch (Throwable) {
            $messages = $load();
        }

        return $this->overrides[$key] = $messages;
    }

    /** Changes with every change to the tenant's strings of $locale; 0 when there are none. */
    public function revision(string $locale): int
    {
        if (! $this->tenants->has()) {
            return 0;
        }

        $rows = $this->rows();

        return isset($rows[$locale]) ? $rows[$locale]->revision : 0;
    }

    /** The language's own name, as users of that language know it. */
    public function label(string $locale): string
    {
        $row = $this->tenants->has() ? ($this->rows()[$locale] ?? null) : null;

        if ($row !== null && $row->is_custom && $row->name !== null) {
            return $row->name;
        }

        if (Pseudo::isPseudo($locale)) {
            return $locale;
        }

        return mb_convert_case(Locale::getDisplayName($locale, $locale) ?: $locale, MB_CASE_TITLE);
    }

    /**
     * Languages users may choose, labeled in their own language.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_map(
            fn (string $locale): array => ['value' => $locale, 'label' => $this->label($locale)],
            $this->locales()->available(),
        );
    }

    /**
     * Every language of the tenant for the administration page.
     *
     * @return list<array{locale: string, name: string, direction: string, kind: string, based_on: string|null, active: bool, is_default: bool, completeness: int, users: int}>
     */
    public function describe(): array
    {
        $locales = $this->locales();
        $source = array_keys($this->catalog->messages($locales->source));
        $total = max(1, count($source));
        $default = Locales::normalize($this->tenants->current()->default_locale);
        $rows = $this->rows();

        $users = [];
        foreach (User::query()->whereNotNull('locale')->pluck('locale') as $locale) {
            if (is_string($locale)) {
                $users[$locale] = ($users[$locale] ?? 0) + 1;
            }
        }

        /** @var array<string, list<string>> $reviewed */
        $reviewed = [];
        $custom = array_keys(array_filter($rows, fn (TenantLanguage $row): bool => $row->is_custom));
        foreach (StringOverride::query()->where('scope', 'tenant')->where('needs_review', false)->whereIn('locale', $custom)->get(['locale', 'identifier']) as $override) {
            $reviewed[$override->locale][] = $override->identifier;
        }

        $languages = [];
        foreach ($locales->known() as $locale) {
            $row = $rows[$locale] ?? null;
            $kind = match (true) {
                $row?->is_custom === true => 'custom',
                Pseudo::isPseudo($locale) => 'pseudo',
                default => 'shipped',
            };

            $present = match ($kind) {
                'custom' => array_intersect($source, $reviewed[$locale] ?? []),
                'pseudo' => $source,
                default => array_intersect($source, array_keys($this->catalog->messages($locale))),
            };

            $languages[] = [
                'locale' => $locale,
                'name' => $this->label($locale),
                'direction' => $locales->direction($locale),
                'kind' => $kind,
                'based_on' => $row?->based_on,
                'active' => $locales->isAvailable($locale),
                'is_default' => $locale === $default,
                'completeness' => (int) floor(count($present) * 100 / $total),
                'users' => $users[$locale] ?? 0,
            ];
        }

        return $languages;
    }

    /**
     * @throws LanguageChangeRefused
     */
    public function setActive(string $locale, bool $active, User $actor): void
    {
        $locale = $this->knownLocale($locale);

        if (! $active && $locale === Locales::normalize($this->tenants->current()->default_locale)) {
            throw LanguageChangeRefused::because('active', 'core:languages.error_default');
        }

        DB::transaction(function () use ($locale, $active, $actor): void {
            $row = $this->rows()[$locale] ?? new TenantLanguage([
                'locale' => $locale,
                'direction' => $this->base->direction($locale),
                'is_custom' => false,
            ]);

            if ($row->exists && $row->is_active === $active) {
                return;
            }

            $row->fill(['is_active' => $active, 'changed_by' => $actor->id])->save();

            $this->audit->record($active ? 'languages.activated' : 'languages.deactivated', $row, [
                'locale' => $locale,
                'users' => User::query()->where('locale', $locale)->count(),
            ], $actor);
        });

        $this->forget();
    }

    /**
     * Copy $from into a new language of the tenant. Every string is copied
     * (from $from, else from its fallbacks) and marked "needs review".
     *
     * @throws LanguageChangeRefused
     */
    public function copy(string $from, string $locale, string $name, string $direction, User $actor): TenantLanguage
    {
        $from = $this->knownLocale($from);
        $locale = Locales::normalize($locale);
        $this->assertNewLocale($locale);

        if (Pseudo::isPseudo($from)) {
            throw LanguageChangeRefused::because('from', 'core:languages.error_source_pseudo');
        }

        $locales = $this->locales();
        $source = $this->catalog->messages($locales->source);
        $chain = $locales->chain($from);
        $now = now();

        $rows = [];
        foreach ($source as $identifier => $sourceMessage) {
            $message = null;
            foreach ($chain as $candidate) {
                $message = $this->overrides($candidate)[$identifier] ?? $this->catalog->message($candidate, $identifier);
                if ($message !== null) {
                    break;
                }
            }

            $rows[] = [
                'id' => (string) Str::ulid(),
                'scope' => 'tenant',
                'tenant_id' => $this->tenants->id(),
                'locale' => $locale,
                'identifier' => $identifier,
                'value' => $message ?? $sourceMessage,
                'source_hash' => self::sourceHash($sourceMessage),
                'needs_review' => true,
                'updated_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $language = DB::transaction(function () use ($from, $locale, $name, $direction, $actor, $rows): TenantLanguage {
            $language = TenantLanguage::query()->create([
                'locale' => $locale,
                'name' => trim($name),
                'direction' => $direction === 'rtl' ? 'rtl' : 'ltr',
                'based_on' => $from,
                'is_custom' => true,
                'is_active' => true,
                'revision' => 1,
                'changed_by' => $actor->id,
            ]);

            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table('string_overrides')->insert($chunk);
            }

            $this->audit->record('languages.copied', $language, [
                'locale' => $locale,
                'based_on' => $from,
                'strings' => count($rows),
            ], $actor);

            return $language;
        });

        $this->forget();

        return $language;
    }

    /**
     * Legal texts need their own permission, also in a file. Texts that are
     * shipped or stored already may come back unchanged.
     *
     * @param  array<string, string>  $messages
     *
     * @throws LanguageChangeRefused
     */
    private function assertLegalUnchanged(string $locale, array $messages, User $actor): void
    {
        $protected = array_filter($messages, StringEditor::isProtected(...), ARRAY_FILTER_USE_KEY);
        if ($protected === [] || app(Permissions::class)->allows($actor, StringEditor::LEGAL_PERMISSION)) {
            return;
        }
        $stored = DB::table('string_overrides')->where('tenant_id', $this->tenants->id())->where('locale', $locale)->whereIn('identifier', array_keys($protected))->pluck('value', 'identifier');
        foreach ($protected as $identifier => $message) {
            $known = [$stored->get($identifier)];
            foreach ($this->base->available() as $shipped) {
                $known[] = $this->catalog->message($shipped, $identifier);
            }
            if (! in_array($message, $known, true)) {
                throw LanguageChangeRefused::because('file', 'core:strings.error_protected');
            }
        }
    }

    /**
     * Import translated strings into one of the tenant's own languages. The
     * import is all or nothing: one invalid message refuses all of them.
     *
     * @param  array<array-key, mixed>  $messages  identifier => ICU message
     * @return int number of strings written
     *
     * @throws LanguageChangeRefused
     */
    public function import(string $locale, array $messages, User $actor): int
    {
        $locale = $this->knownLocale($locale);
        $row = $this->rows()[$locale] ?? null;

        if ($row === null || ! $row->is_custom) {
            throw LanguageChangeRefused::because('file', 'core:languages.error_import_shipped');
        }

        $source = $this->catalog->messages($this->base->source);
        $reasons = [];
        $valid = [];

        foreach ($messages as $identifier => $message) {
            $identifier = (string) $identifier;
            $reason = $this->checkMessage($identifier, $message, $source, $locale);

            if ($reason !== null) {
                $reasons[] = $reason;
                if (count($reasons) >= self::MAX_REASONS) {
                    break;
                }

                continue;
            }

            /** @var string $message */
            $valid[$identifier] = $message;
        }

        if ($reasons !== []) {
            throw new LanguageChangeRefused('file', $reasons);
        }

        $this->assertLegalUnchanged($locale, $valid, $actor);

        if ($valid === []) {
            throw LanguageChangeRefused::because('file', 'core:languages.error_import_empty');
        }

        $now = now();
        $rows = [];
        foreach ($valid as $identifier => $message) {
            $rows[] = [
                'id' => (string) Str::ulid(),
                'scope' => 'tenant',
                'tenant_id' => $this->tenants->id(),
                'locale' => $locale,
                'identifier' => $identifier,
                'value' => $message,
                'source_hash' => self::sourceHash($source[$identifier]),
                'needs_review' => false,
                'updated_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($row, $rows, $locale, $actor): void {
            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table('string_overrides')->upsert(
                    $chunk,
                    ['scope', 'tenant_id', 'locale', 'identifier'],
                    ['value', 'source_hash', 'needs_review', 'updated_by', 'updated_at'],
                );
            }

            $row->fill(['revision' => $row->revision + 1, 'changed_by' => $actor->id])->save();

            $this->audit->record('languages.imported', $row, ['locale' => $locale, 'strings' => count($rows)], $actor);
        });

        $this->forget();

        return count($rows);
    }

    /**
     * The strings of $locale as the tenant has them, with the en source for
     * the translator.
     *
     * @return array{format: string, version: int, locale: string, name: string, direction: string, based_on: string|null, messages: array<string, string>, source: array<string, string>}
     *
     * @throws LanguageChangeRefused
     */
    public function export(string $locale): array
    {
        $locale = $this->knownLocale($locale);
        $locales = $this->locales();
        $source = $this->catalog->messages($locales->source);
        $own = [...$this->catalog->messages($locale), ...$this->overrides($locale)];

        return [
            'format' => self::FORMAT,
            'version' => 1,
            'locale' => $locale,
            'name' => $this->label($locale),
            'direction' => $locales->direction($locale),
            'based_on' => $locales->parentOf($locale),
            'messages' => array_intersect_key($own, $source),
            'source' => $source,
        ];
    }

    /** The tenant's row for $locale, if it differs from the shipped language. */
    public function row(string $locale): ?TenantLanguage
    {
        return $this->rows()[$locale] ?? null;
    }

    /**
     * Note a change to the tenant's strings of $locale: the bundle version
     * and the override cache move on. Creates the row for a shipped language.
     */
    public function touch(string $locale, User $actor): TenantLanguage
    {
        $row = $this->rows()[$locale] ?? new TenantLanguage([
            'locale' => $locale,
            'direction' => $this->base->direction($locale),
            'is_custom' => false,
            'is_active' => true,
            'revision' => 0,
        ]);

        $row->fill(['revision' => $row->revision + 1, 'changed_by' => $actor->id])->save();

        return $row;
    }

    /** Drop what this service remembered for the current request. */
    public function forget(): void
    {
        $this->rows = [];
        $this->views = [];
        $this->overrides = [];
    }

    public static function sourceHash(string $message): string
    {
        return hash('xxh128', $message);
    }

    /**
     * @param  array<string, string>  $source
     * @return array{0: string, 1: array<string, string|int>}|null
     */
    public function checkMessage(string $identifier, mixed $message, array $source, string $locale): ?array
    {
        if (! isset($source[$identifier])) {
            return ['core:languages.import_unknown', ['id' => $identifier]];
        }

        if (! is_string($message) || trim($message) === '') {
            return ['core:languages.import_empty_message', ['id' => $identifier]];
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return ['core:languages.import_too_long', ['id' => $identifier, 'max' => self::MAX_MESSAGE_LENGTH]];
        }

        try {
            new MessageFormatter(str_replace('-', '_', $locale), $message);
        } catch (IntlException|ValueError) {
            return ['core:languages.import_invalid', ['id' => $identifier]];
        }

        $unknown = array_diff(MessageArguments::of($message), MessageArguments::of($source[$identifier]));
        if ($unknown !== []) {
            return ['core:languages.import_arguments', ['id' => $identifier, 'names' => implode(', ', $unknown)]];
        }

        return null;
    }

    /**
     * @throws LanguageChangeRefused
     */
    private function assertNewLocale(string $locale): void
    {
        if (! preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/', $locale)) {
            throw LanguageChangeRefused::because('locale', 'core:languages.error_code_format');
        }

        if ($this->base->isRefused($locale)) {
            throw LanguageChangeRefused::because('locale', 'core:languages.error_code_refused');
        }

        if ($this->locales()->isKnown($locale) || isset($this->rows()[$locale])) {
            throw LanguageChangeRefused::because('locale', 'core:languages.error_code_exists');
        }

        // ICU must know the language (and region), or plurals and formats cannot work.
        $language = Locales::base($locale);
        $region = substr($locale, strlen($language) + 1);

        if (Locale::getDisplayLanguage($language, 'en') === $language
            || ($region !== '' && Locale::getDisplayRegion('und-'.$region, 'en') === $region)) {
            throw LanguageChangeRefused::because('locale', 'core:languages.error_code_unknown');
        }
    }

    /**
     * @throws LanguageChangeRefused
     */
    public function knownLocale(string $locale): string
    {
        $locale = Locales::normalize($locale);

        if (! $this->locales()->isKnown($locale)) {
            throw LanguageChangeRefused::because('locale', 'core:languages.error_unknown');
        }

        return $locale;
    }

    /**
     * @return array<string, TenantLanguage>
     */
    private function rows(): array
    {
        $tenantId = $this->tenants->id();

        if (! isset($this->rows[$tenantId])) {
            $this->rows[$tenantId] = [];
            foreach (TenantLanguage::query()->get() as $row) {
                $this->rows[$tenantId][$row->locale] = $row;
            }
        }

        return $this->rows[$tenantId];
    }
}

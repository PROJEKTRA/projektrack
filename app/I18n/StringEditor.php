<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

use App\Audit\AuditLog;
use App\Authorization\Permissions;
use App\Models\StringOverride;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin language customization (S-0918, ADR-0005 section 4): a tenant replaces
 * any string in any of its languages with its own text, for example
 * "Activity report" instead of "Service report". The override applies to
 * every screen, e-mail and export of that tenant, survives updates, and is
 * flagged when the en source text changes after it was written.
 */
final class StringEditor
{
    public const int PER_PAGE = 50;

    /** @var list<string> */
    public const array FILTERS = ['all', 'customized', 'untranslated', 'source_changed'];

    /** Legal notices on documents need their own permission (ADR-0004). */
    public const string LEGAL_PERMISSION = 'tax.legal_notice.override';

    public function __construct(
        private readonly TenantLanguages $languages,
        private readonly StringCatalog $catalog,
        private readonly Permissions $permissions,
        private readonly AuditLog $audit,
    ) {}

    /**
     * One page of strings of $locale, with the en source, the shipped text,
     * the tenant's override and its review state side by side.
     *
     * @return array{rows: list<array{identifier: string, component: string, source: string, original: string|null, override: string|null, needs_review: bool, source_changed: bool, protected: bool}>, total: int, page: int, pages: int, components: list<string>}
     *
     * @throws LanguageChangeRefused
     */
    public function search(string $locale, string $query = '', string $component = '', string $filter = 'all', int $page = 1): array
    {
        $locale = $this->editable($locale);
        $locales = $this->languages->locales();
        $source = $this->catalog->messages($locales->source);
        $chain = array_values(array_filter($locales->chain($locale), fn (string $l): bool => $l !== $locales->source || $locale === $locales->source));
        $records = $this->records($locale);
        $query = mb_strtolower(trim($query));

        $rows = [];
        $components = [];
        foreach ($source as $identifier => $sourceMessage) {
            $rowComponent = explode(':', $identifier, 2)[0];
            $components[$rowComponent] = true;

            if ($component !== '' && $rowComponent !== $component) {
                continue;
            }

            $record = $records[$identifier] ?? null;
            $original = $this->original($identifier, $locale, $chain);
            $row = [
                'identifier' => $identifier,
                'component' => $rowComponent,
                'source' => $sourceMessage,
                'original' => $original,
                'override' => $record['value'] ?? null,
                'needs_review' => $record['needs_review'] ?? false,
                'source_changed' => $record !== null && $record['source_hash'] !== null
                    && $record['source_hash'] !== TenantLanguages::sourceHash($sourceMessage),
                'protected' => self::isProtected($identifier),
            ];

            $matches = match ($filter) {
                'customized' => $row['override'] !== null && ! $row['needs_review'],
                'untranslated' => ($row['override'] === null && $original === null) || $row['needs_review'],
                'source_changed' => $row['source_changed'],
                default => true,
            };

            if ($matches && ($query === '' || $this->contains($row, $query))) {
                $rows[] = $row;
            }
        }

        $total = count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $names = array_keys($components);
        sort($names);

        return [
            'rows' => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'components' => $names,
        ];
    }

    /**
     * Replace one string of $locale for the tenant.
     *
     * @throws LanguageChangeRefused
     */
    public function save(string $locale, string $identifier, string $value, User $actor): void
    {
        $locale = $this->editable($locale);
        $this->assertMayEdit($identifier, $actor);

        $source = $this->catalog->messages($this->languages->locales()->source);
        $reason = $this->languages->checkMessage($identifier, $value, $source, $locale);

        if ($reason !== null) {
            throw new LanguageChangeRefused('value', [$reason]);
        }

        DB::transaction(function () use ($locale, $identifier, $value, $source, $actor): void {
            $override = StringOverride::query()->firstOrNew(['scope' => 'tenant', 'locale' => $locale, 'identifier' => $identifier]);
            $override->fill([
                'value' => $value,
                'source_hash' => TenantLanguages::sourceHash($source[$identifier]),
                'needs_review' => false,
                'updated_by' => $actor->id,
            ])->save();

            $this->languages->touch($locale, $actor);
            $this->audit->record('strings.overridden', $override, ['locale' => $locale, 'identifier' => $identifier], $actor);
        });

        $this->languages->forget();
    }

    /**
     * Remove the tenant's text: the shipped text applies again (for an own
     * language: the language it was copied from).
     *
     * @throws LanguageChangeRefused
     */
    public function reset(string $locale, string $identifier, User $actor): void
    {
        $locale = $this->editable($locale);
        $this->assertMayEdit($identifier, $actor);

        DB::transaction(function () use ($locale, $identifier, $actor): void {
            $deleted = StringOverride::query()
                ->where('scope', 'tenant')
                ->where('locale', $locale)
                ->where('identifier', $identifier)
                ->delete();

            if ($deleted > 0) {
                $this->languages->touch($locale, $actor);
                $this->audit->record('strings.reset', ['string_overrides', null], ['locale' => $locale, 'identifier' => $identifier], $actor);
            }
        });

        $this->languages->forget();
    }

    /**
     * Pseudo-locales are generated from en and have no texts of their own.
     *
     * @throws LanguageChangeRefused
     */
    private function editable(string $locale): string
    {
        $locale = $this->languages->knownLocale($locale);

        if (Pseudo::isPseudo($locale)) {
            throw LanguageChangeRefused::because('locale', 'core:languages.error_unknown');
        }

        return $locale;
    }

    public static function isProtected(string $identifier): bool
    {
        return str_contains(explode(':', $identifier, 2)[1] ?? '', 'legal_notice');
    }

    /**
     * @throws LanguageChangeRefused
     */
    private function assertMayEdit(string $identifier, User $actor): void
    {
        if (self::isProtected($identifier) && ! $this->permissions->allows($actor, self::LEGAL_PERMISSION)) {
            throw LanguageChangeRefused::because('value', 'core:strings.error_protected');
        }
    }

    /**
     * The text without this tenant's override of $locale: the shipped text of
     * $locale, or what its fallbacks (and, for a copy, its parent) give.
     *
     * @param  list<string>  $chain
     */
    private function original(string $identifier, string $locale, array $chain): ?string
    {
        foreach ($chain as $candidate) {
            $message = ($candidate === $locale ? null : ($this->languages->overrides($candidate)[$identifier] ?? null))
                ?? $this->catalog->message($candidate, $identifier);

            if ($message !== null) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{value: string, needs_review: bool, source_hash: string|null}>
     */
    private function records(string $locale): array
    {
        $records = [];
        foreach (StringOverride::query()->where('scope', 'tenant')->where('locale', $locale)->get() as $override) {
            $records[$override->identifier] = [
                'value' => $override->value,
                'needs_review' => $override->needs_review,
                'source_hash' => $override->source_hash,
            ];
        }

        return $records;
    }

    /**
     * @param  array{identifier: string, source: string, original: string|null, override: string|null}  $row
     */
    private function contains(array $row, string $query): bool
    {
        foreach ([$row['identifier'], $row['source'], $row['original'] ?? '', $row['override'] ?? ''] as $text) {
            if (str_contains(mb_strtolower($text), $query)) {
                return true;
            }
        }

        return false;
    }
}

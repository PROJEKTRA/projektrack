<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\I18n\LanguageChangeRefused;
use App\I18n\StringCatalog;
use App\I18n\StringEditor;
use App\I18n\TenantLanguages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > Languages > Texts (S-0918): search the strings of one language and
 * replace any of them with the tenant's own text. Every route requires
 * core.strings.customize; legal notices also tax.legal_notice.override.
 */
class StringController extends Controller
{
    public function index(Request $request, StringEditor $editor, TenantLanguages $languages, string $locale): Response
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'component' => ['nullable', 'string', 'max:41'],
            'filter' => ['nullable', Rule::in(StringEditor::FILTERS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = [
            'q' => $request->string('q')->toString(),
            'component' => $request->string('component')->toString(),
            'filter' => $request->string('filter', 'all')->toString(),
        ];

        $result = $this->refusalsAsErrors(fn () => $editor->search(
            $locale,
            $filters['q'],
            $filters['component'],
            $filters['filter'],
            $request->integer('page', 1),
        ));

        $locale = $languages->knownLocale($locale);

        return Inertia::render('admin/Strings', [
            'language' => [
                'locale' => $locale,
                'name' => $languages->label($locale),
                'direction' => $languages->locales()->direction($locale),
            ],
            'filters' => $filters,
            ...$result,
        ]);
    }

    /** Replace one string with the tenant's own text. */
    public function update(Request $request, StringEditor $editor, string $locale): RedirectResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:160', 'regex:'.StringCatalog::IDENTIFIER],
            'value' => ['required', 'string'],
        ]);

        $this->refusalsAsErrors(fn () => $editor->save(
            $locale,
            $request->string('identifier')->toString(),
            $request->string('value')->toString(),
            $this->currentUser($request),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:strings.saved')]);

        return back();
    }

    /** Remove the tenant's text, so the shipped one applies again. */
    public function destroy(Request $request, StringEditor $editor, string $locale): RedirectResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:160', 'regex:'.StringCatalog::IDENTIFIER],
        ]);

        $this->refusalsAsErrors(fn () => $editor->reset($locale, $request->string('identifier')->toString(), $this->currentUser($request)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:strings.reset_done')]);

        return back();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function refusalsAsErrors(callable $action): mixed
    {
        try {
            return $action();
        } catch (LanguageChangeRefused $refused) {
            throw ValidationException::withMessages([
                $refused->field => implode("\n", array_map(
                    fn (array $reason): string => __($reason[0], $reason[1]),
                    $refused->reasons,
                )),
            ]);
        }
    }
}

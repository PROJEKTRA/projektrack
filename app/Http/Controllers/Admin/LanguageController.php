<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\I18n\LanguageChangeRefused;
use App\I18n\TenantLanguages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > Languages (S-0921): which languages the tenant offers, and its own
 * languages copied from another one and localized by export and import.
 * Every route requires core.strings.customize.
 */
class LanguageController extends Controller
{
    /** Largest accepted import file, in kilobytes. */
    private const int MAX_IMPORT_KB = 2048;

    public function index(TenantLanguages $languages): Response
    {
        return Inertia::render('admin/Languages', [
            'languages' => $languages->describe(),
        ]);
    }

    /** Copy a language into a new language of the tenant. */
    public function store(Request $request, TenantLanguages $languages): RedirectResponse
    {
        $request->validate([
            'from' => ['required', 'string', 'max:16'],
            'locale' => ['required', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:60'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
        ]);

        $language = $this->refusalsAsErrors(fn () => $languages->copy(
            $request->string('from')->toString(),
            $request->string('locale')->toString(),
            $request->string('name')->toString(),
            $request->string('direction')->toString(),
            $this->currentUser($request),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:languages.copied', ['name' => $language->name ?? $language->locale])]);

        return to_route('admin.languages.index');
    }

    /** Activate or deactivate a language for the tenant's users. */
    public function update(Request $request, TenantLanguages $languages, string $locale): RedirectResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);
        $active = $request->boolean('active');

        $this->refusalsAsErrors(fn () => $languages->setActive($locale, $active, $this->currentUser($request)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __($active ? 'core:languages.activated' : 'core:languages.deactivated', [
            'name' => $languages->label($locale),
        ])]);

        return to_route('admin.languages.index');
    }

    /** Download the language's strings as JSON for translation. */
    public function export(TenantLanguages $languages, string $locale): JsonResponse
    {
        $export = $this->refusalsAsErrors(fn () => $languages->export($locale));

        return response()
            ->json($export, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'attachment; filename="strings-'.$export['locale'].'.json"')
            ->header('Cache-Control', 'no-store');
    }

    /** Upload translated strings into one of the tenant's own languages. */
    public function import(Request $request, TenantLanguages $languages, string $locale): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_IMPORT_KB, 'mimetypes:application/json,text/plain'],
        ]);

        $file = $request->file('file');
        $data = $file instanceof UploadedFile ? json_decode((string) $file->get(), true) : null;

        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => __('core:languages.error_import_json')]);
        }

        // Accept an export of this application or a plain { identifier: message } object.
        $messages = ($data['format'] ?? null) === TenantLanguages::FORMAT && is_array($data['messages'] ?? null)
            ? $data['messages']
            : $data;

        $count = $this->refusalsAsErrors(fn () => $languages->import($locale, $messages, $this->currentUser($request)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('core:languages.imported', ['count' => $count])]);

        return to_route('admin.languages.index');
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
            // One message per field reaches the page, so all reasons go into it, one per line.
            throw ValidationException::withMessages([
                $refused->field => implode("\n", array_map(
                    fn (array $reason): string => __($reason[0], $reason[1]),
                    $refused->reasons,
                )),
            ]);
        }
    }
}

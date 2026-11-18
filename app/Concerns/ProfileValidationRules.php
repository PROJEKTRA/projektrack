<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Concerns;

use App\I18n\Formats;
use App\I18n\TenantLanguages;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?string $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'locale' => $this->localeRules(),
            'format_locale' => ['sometimes', 'nullable', 'string', Rule::in(Formats::offered())],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
        ];
    }

    /**
     * The user's own language (S-0916), one the tenant offers (S-0921). Empty means: use the tenant language.
     *
     * @return array<int, ValidationRule|string|In>
     */
    protected function localeRules(): array
    {
        return ['sometimes', 'nullable', 'string', Rule::in(app(TenantLanguages::class)->locales()->available())];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?string $userId = null): array
    {
        // E-mail addresses are unique per tenant, not per instance (ADR-0006).
        $unique = Rule::unique(User::class)->where('tenant_id', app(TenantContext::class)->id());

        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null ? $unique : $unique->ignore($userId),
        ];
    }
}

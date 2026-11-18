<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Translation\Translator;

/**
 * Bridges Laravel's translator to the string system (ADR-0005 section 3), so
 * `__()`, validation, Fortify, password reset and framework mail texts all
 * come from identifiers:
 *
 * - `__('core:common.save')` resolves the identifier through {@see Strings}.
 * - Framework groups (`validation.required`, `auth.failed`, `passwords.sent`,
 *   `pagination.next`) map to `core:<group>.<key>`. These messages keep
 *   Laravel's `:placeholder` syntax, because the validator fills placeholders
 *   itself after the lookup.
 * - The few sentence keys used by framework mail templates map to `core:email.*`.
 *
 * Anything else falls through to Laravel unchanged.
 */
final class StringTranslator extends Translator
{
    private const array BRIDGED_GROUPS = ['auth', 'pagination', 'passwords', 'validation'];

    /** Sentence keys used by framework mail templates and notifications. */
    private const array SENTENCES = [
        'Hello!' => 'core:email.hello',
        'Whoops!' => 'core:email.whoops',
        'Regards,' => 'core:email.regards',
        'All rights reserved.' => 'core:email.rights_reserved',
        "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:" => 'core:email.trouble_clicking',
        'Reset Password Notification' => 'core:email.reset_password.subject',
        'You are receiving this email because we received a password reset request for your account.' => 'core:email.reset_password.intro',
        'Reset Password' => 'core:email.reset_password.action',
        'This password reset link will expire in :count minutes.' => 'core:email.reset_password.expires',
        'If you did not request a password reset, no further action is required.' => 'core:email.reset_password.not_you',
        'Verify Email Address' => 'core:email.verify.subject',
        'Please click the button below to verify your email address.' => 'core:email.verify.intro',
        'If you did not create an account, no further action is required.' => 'core:email.verify.not_you',
    ];

    /** @var Closure(): Strings */
    private Closure $strings;

    /**
     * @param  Closure(): Strings  $strings  resolved lazily, after the container is ready
     */
    public function setStrings(Closure $strings): void
    {
        $this->strings = $strings;
    }

    /**
     * @param  string  $key
     * @param  array<string, mixed>  $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return string|array<array-key, mixed>
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $strings = ($this->strings)();

        if (preg_match(StringCatalog::IDENTIFIER, $key)) {
            return $strings->get($key, $replace, $locale);
        }

        if (isset(self::SENTENCES[$key])) {
            return $this->bridged($strings, self::SENTENCES[$key], $replace, $locale) ?? parent::get($key, $replace, $locale, $fallback);
        }

        $group = explode('.', $key, 2)[0];

        if (in_array($group, self::BRIDGED_GROUPS, true) && str_contains($key, '.')) {
            $identifier = 'core:'.$key;

            $line = $this->bridged($strings, $identifier, $replace, $locale);
            if ($line !== null) {
                return $line;
            }

            $children = $this->children($strings, $identifier, $locale);
            if ($children !== []) {
                return $children;
            }
        }

        return parent::get($key, $replace, $locale, $fallback);
    }

    /**
     * Resolve and format, then fill any remaining Laravel `:placeholders`.
     *
     * @param  array<string, mixed>  $replace
     */
    private function bridged(Strings $strings, string $identifier, array $replace, ?string $locale): ?string
    {
        $resolved = $strings->resolve($identifier, $strings->chain($locale));

        if ($resolved === null) {
            return null;
        }

        $text = $strings->format($identifier, $resolved[0], $replace, $resolved[1]);

        return $text === null ? null : $this->makeReplacements($text, $replace);
    }

    /**
     * `validation.attributes` and `validation.custom` are read as arrays by the
     * validator: collect every identifier below the prefix.
     *
     * @return array<array-key, mixed>
     */
    private function children(Strings $strings, string $identifier, ?string $locale): array
    {
        $prefix = $identifier.'.';
        $flat = [];

        foreach (array_reverse($strings->chain($locale)) as $chainLocale) {
            foreach ($strings->catalogMessages($chainLocale) as $id => $message) {
                if (str_starts_with($id, $prefix)) {
                    $flat[substr($id, strlen($prefix))] = $message;
                }
            }
        }

        return Arr::undot($flat);
    }
}

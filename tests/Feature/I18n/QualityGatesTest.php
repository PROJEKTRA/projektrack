<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\I18n\Locales;
use App\I18n\Pseudo;
use App\I18n\StringCatalog;
use App\I18n\Strings;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia;

/*
 * S-0917: PHP and browser formatting parity, pseudo-locales.
 * The lint gates themselves are tested in scripts/i18n/check.test.ts.
 */

describe('formatting parity with the browser', function () {
    $corpus = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/i18n/parity.json'), true);

    it('renders :dataset like intl-messageformat', function (string $locale, string $message, array $args, string $expected) {
        expect(app(Strings::class)->format('test:case', $message, $args, $locale))->toBe($expected);
    })->with(array_map(
        fn (array $case): array => [$case['locale'], $case['message'], $case['args'], $case['expected']],
        $corpus['cases'],
    ));

    it('runs on an ICU version at least as new as the documented minimum', function () {
        expect(version_compare(INTL_ICU_VERSION, '74.0', '>='))->toBeTrue();
    });
});

describe('pseudo-locales', function () {
    it('accents and elongates literal text and keeps ICU syntax and placeholders', function () {
        expect(Pseudo::transform('Save'))->toBe('[!! Šááṽéé !!]')
            ->and(Pseudo::transform('Hello {name}'))->toBe('[!! Ĥééļļóó {name} !!]')
            ->and(Pseudo::transform('{count, plural, one {# day} other {# days}}'))
            ->toBe('[!! {count, plural, one {# ðááý} other {# ðááýš}} !!]')
            ->and(Pseudo::transform('Entry {seq, number, ::group-off}'))->toBe('[!! ÉÉñţŕý {seq, number, ::group-off} !!]')
            ->and(Pseudo::transform('The :attribute field'))->toBe('[!! Ţĥéé :attribute ƒííééļð !!]')
            ->and(Pseudo::transform("it''s '{literal}'"))->toBe("[!! ííţ''š '{literal}' !!]");
    });

    it('keeps every shipped message valid with the same arguments', function () {
        $catalog = app(StringCatalog::class);

        foreach ($catalog->messages('en') as $identifier => $message) {
            $pseudo = $catalog->message('xx', $identifier);

            expect($pseudo)->toStartWith('[!! ')->toEndWith(' !!]');

            try {
                new MessageFormatter('en', (string) $pseudo);
            } catch (IntlException $e) {
                $this->fail("xx {$identifier}: {$e->getMessage()}");
            }
        }
    });

    it('formats pseudo text with en plural rules', function () {
        expect(app(Strings::class)->get('core:two_factor.recovery_codes_hidden', ['count' => 1], 'xx'))
            ->toContain('1 úúñúúšééð çóóðéé')
            ->and(app(Strings::class)->get('core:two_factor.recovery_codes_hidden', ['count' => 3], 'xx'))
            ->toContain('3 úúñúúšééð çóóðééš');
    });

    it('lets a tester select xx and xx-rtl', function () {
        $user = User::factory()->create(['locale' => 'xx-rtl']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="xx-rtl" dir="rtl"', false)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('strings.locale', 'xx-rtl')->where('strings.dir', 'rtl'));

        $bundle = app(Strings::class)->bundle(['xx', 'bs', 'en']);
        expect($bundle['messages']['core:common.save'])->toStartWith('[!! ');

        App::setLocale('xx');
        expect(Validator::make([], ['email' => 'required'])->errors()->first('email'))->toStartWith('[!! ');
    });

    it('is hidden when switched off, as in production', function () {
        $locales = new Locales(['bs', 'en'], 'bs', 'en', ['xx-rtl'], ['he', 'iw'], pseudo: false);

        expect($locales->available())->toBe(['bs', 'en'])
            ->and($locales->isAvailable('xx'))->toBeFalse()
            ->and(config('i18n.pseudo'))->toBeTrue();
    });
});

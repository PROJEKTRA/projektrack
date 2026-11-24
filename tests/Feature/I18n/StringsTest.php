<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\I18n\Locales;
use App\I18n\StringCatalog;
use App\I18n\Strings;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Notifications\TwoFactorWasReset;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia;
use Psr\Log\NullLogger;
use Tests\Support\FakeModules;

/*
 * S-0916: one string layer for backend and frontend, ICU MessageFormat, CLDR
 * plurals and the fallback chain user, tenant, bs, en.
 */

/**
 * Replace the shipped strings with fixtures for one test.
 *
 * @param  list<string>  $locales
 * @param  array<string, array<string, string>>  $core  locale => key => message
 */
function useStringFixtures(array $locales, array $core): string
{
    $root = sys_get_temp_dir().'/ptk-lang-'.bin2hex(random_bytes(6));

    foreach ($core as $locale => $messages) {
        mkdir("{$root}/{$locale}", recursive: true);
        file_put_contents("{$root}/{$locale}/core.json", (string) json_encode($messages));
    }

    app()->instance(Locales::class, new Locales($locales, 'bs', 'en', ['ar', 'fa'], ['he', 'iw']));
    app()->instance(StringCatalog::class, new StringCatalog($root, app(ModuleRegistry::class), "{$root}/cache", new NullLogger));
    app()->forgetScopedInstances();

    return $root;
}

function strings(): Strings
{
    return app(Strings::class);
}

describe('Bosnian plural rules (CLDR one, few, other)', function () {
    it('formats {count} as :expected', function (int $count, string $expected) {
        $pattern = '{count, plural, one {# sat} few {# sata} other {# sati}}';

        expect(strings()->format('test:hours', $pattern, ['count' => $count], 'bs'))->toBe($expected);
    })->with([
        [1, '1 sat'],
        [2, '2 sata'],
        [4, '4 sata'],
        [5, '5 sati'],
        [11, '11 sati'],
        [12, '12 sati'],
        [21, '21 sat'],
        [22, '22 sata'],
        [25, '25 sati'],
        [101, '101 sat'],
    ]);
});

describe('one layer for backend and frontend', function () {
    it('resolves the same identifier through __(), the string service and the bundle', function () {
        App::setLocale('bs');

        expect(__('core:flash.profile_updated'))->toBe('Profil je ažuriran.')
            ->and(strings()->get('core:flash.profile_updated'))->toBe('Profil je ažuriran.')
            ->and(strings()->bundle(['bs', 'en'])['messages']['core:flash.profile_updated'])->toBe('Profil je ažuriran.');
    });

    it('serves validation messages and attribute names from identifiers', function () {
        App::setLocale('bs');
        $bs = Validator::make([], ['email' => 'required'])->errors()->first('email');

        App::setLocale('en');
        $en = Validator::make(['email' => 'x'], ['email' => 'email'])->errors()->first('email');

        expect($bs)->toBe('Polje e-mail adresa je obavezno.')
            ->and($en)->toBe('The e-mail address field must be a valid email address.');
    });

    it('formats framework messages with ICU plurals', function () {
        App::setLocale('bs');

        expect(__('auth.throttle', ['seconds' => 3, 'minutes' => 1]))->toBe('Previše pokušaja prijave. Pokušajte ponovo za 3 sekunde.')
            ->and(__('auth.throttle', ['seconds' => 21, 'minutes' => 1]))->toBe('Previše pokušaja prijave. Pokušajte ponovo za 21 sekundu.');
    });

    it('resolves module strings with the module key as component', function () {
        FakeModules::install(['timesheets' => []]);
        app()->forgetInstance(StringCatalog::class);
        $dir = app(ModuleRegistry::class)->get('timesheets')->path;

        foreach (['en' => '{count, plural, one {# hour booked} other {# hours booked}}',
            'bs' => '{count, plural, one {# sat evidentiran} few {# sata evidentirana} other {# sati evidentirano}}'] as $locale => $message) {
            mkdir("{$dir}/lang/{$locale}", recursive: true);
            file_put_contents("{$dir}/lang/{$locale}/timesheets.json", (string) json_encode(['hours_booked' => $message]));
        }

        App::setLocale('bs');

        expect(__('timesheets:hours_booked', ['count' => 21]))->toBe('21 sat evidentiran')
            ->and(__('timesheets:hours_booked', ['count' => 2]))->toBe('2 sata evidentirana')
            ->and(strings()->get('timesheets:hours_booked', ['count' => 5], 'en'))->toBe('5 hours booked');
    });

    it('sends mail in the recipient language', function () {
        $user = User::factory()->create(['locale' => 'en']);
        App::setLocale('bs');
        $mail = (new TwoFactorWasReset)->toMail($user);

        expect($user->preferredLocale())->toBe('en')
            ->and($mail->subject)->toBe('Your two-factor authentication for '.config('brand.name').' was reset')
            ->and(User::factory()->create(['locale' => null])->preferredLocale())->toBe('bs');
    });
});

describe('fallback chain without raw identifiers', function () {
    beforeEach(function () {
        useStringFixtures(['bs', 'en', 'de', 'fr'], [
            'en' => ['a' => 'A en', 'b' => 'B en', 'c' => 'C en', 'n' => '{count, plural, one {# day} other {# days}}', 'string_missing' => 'missing en'],
            'bs' => ['a' => 'A bs', 'b' => 'B bs', 'string_missing' => 'nedostaje bs'],
            'de' => ['a' => 'A de'],
            'fr' => [],
        ]);
        $this->tenant->update(['default_locale' => 'fr']);
    });

    it('builds the chain user, tenant, bs, en', function () {
        expect(strings()->chain('de'))->toBe(['de', 'fr', 'bs', 'en'])
            ->and(strings()->chain('de-AT'))->toBe(['de', 'fr', 'bs', 'en'])
            ->and(strings()->chain('xx'))->toBe(['fr', 'bs', 'en']);
    });

    it('shows bs when the user and tenant language lack a string, otherwise en', function () {
        expect(strings()->get('core:a', [], 'de'))->toBe('A de')
            ->and(strings()->get('core:b', [], 'de'))->toBe('B bs')
            ->and(strings()->get('core:c', [], 'de'))->toBe('C en');
    });

    it('formats a fallback message with the plural rules of the language it came from', function () {
        $bundle = strings()->bundle(['de', 'fr', 'bs', 'en']);

        expect($bundle['sources'])->toMatchArray(['core:b' => 'bs', 'core:c' => 'en', 'core:n' => 'en'])
            ->and($bundle['sources'])->not->toHaveKey('core:a')
            ->and(strings()->get('core:n', ['count' => 2], 'de'))->toBe('2 days');
    });

    it('logs a missing string once per identifier, language and day', function () {
        Log::spy();
        app()->forgetScopedInstances();

        strings()->get('core:b', [], 'de');
        strings()->get('core:b', [], 'de');
        app()->forgetScopedInstances();
        strings()->get('core:b', [], 'de');

        Log::shouldHaveReceived('warning')->with('String missing in locale, fallback used', ['identifier' => 'core:b', 'locale' => 'de'])->once();
        Log::shouldHaveReceived('warning')->with('String missing in locale, fallback used', ['identifier' => 'core:b', 'locale' => 'fr'])->once();
    });

    it('never shows a raw identifier', function () {
        expect(strings()->get('core:does_not_exist', [], 'de'))->toBe('nedostaje bs')
            ->and(__('core:does_not_exist'))->not->toContain('core:');
    });

    it('treats an invalid message like a missing one', function () {
        useStringFixtures(['bs', 'en'], [
            'en' => ['broken' => '{count, plural, one {x}', 'string_missing' => 'missing en'],
            'bs' => ['string_missing' => 'nedostaje bs'],
        ]);

        expect(strings()->get('core:broken', ['count' => 1], 'bs'))->toBe('nedostaje bs');
    });
});

describe('locales', function () {
    it('defaults a new installation to bs', function () {
        expect(file_get_contents(base_path('.env.example')))->toMatch('/^APP_LOCALE=bs\r?$/m')
            ->and(config('app.locale'))->toBe('bs')
            ->and(config('i18n.default'))->toBe('bs')
            ->and($this->tenant->default_locale)->toBe('bs');

        $this->get(route('login'))->assertOk()->assertSee('lang="bs"', false);
    });

    it('uses the signed-in user language for the page', function () {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('strings.locale', 'en')->where('strings.dir', 'ltr'));
    });

    it('lets users choose their language in the profile', function () {
        $user = User::factory()->create();
        $fields = ['name' => $user->name, 'email' => $user->email];

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('locales.0', ['value' => 'bs', 'label' => 'Bosanski']));

        $this->actingAs($user)->patch(route('profile.update'), [...$fields, 'locale' => 'en'])
            ->assertRedirect(route('profile.edit'));
        expect($user->refresh()->locale)->toBe('en');

        $this->actingAs($user)->patch(route('profile.update'), [...$fields, 'locale' => 'he'])
            ->assertSessionHasErrors('locale');
        expect($user->refresh()->locale)->toBe('en');
    });

    it('never offers Hebrew', function () {
        $locales = new Locales(['bs', 'en', 'he', 'iw'], 'bs', 'en', [], ['he', 'iw']);

        expect($locales->available())->toBe(['bs', 'en'])
            ->and($locales->chain('he'))->toBe(['bs', 'en']);
    });

    it('isolates arguments in right-to-left languages', function () {
        expect(strings()->format('test:ticket', 'التذكرة {key}', ['key' => 'PTK-123'], 'ar'))->toBe("التذكرة \u{2068}PTK-123\u{2069}")
            ->and(strings()->format('test:ticket', 'Ticket {key}', ['key' => 'PTK-123'], 'en'))->toBe('Ticket PTK-123');
    });
});

describe('versioned bundle', function () {
    function bundleMeta(): array
    {
        $meta = null;
        test()->get(route('login'))->assertInertia(function (AssertableInertia $page) use (&$meta) {
            $meta = $page->toArray()['props']['strings'];
        });

        return $meta;
    }

    it('serves the resolved strings of the chain, cacheable and without a session', function () {
        $meta = bundleMeta();
        $response = $this->get($meta['url']);

        $response->assertOk()
            ->assertJsonPath('locale', 'bs')
            ->assertJsonPath('version', $meta['version'])
            ->assertHeader('X-Robots-Tag');

        expect($response->json('messages')['core:flash.profile_updated'] ?? null)->toBe('Profil je ažuriran.')
            ->and($response->headers->get('Cache-Control'))->toContain('immutable')
            ->and($response->headers->getCookies())->toBe([])
            ->and($response->json('messages'))->not->toHaveKey('core:validation.required');
    });

    it('does not let the browser keep an outdated version', function () {
        $this->get(route('strings.bundle', ['chain' => 'bs.en', 'version' => str_repeat('0', 16)]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    });

    it('refuses unknown or non-canonical chains', function (string $chain) {
        $this->get(route('strings.bundle', ['chain' => $chain, 'version' => str_repeat('a', 16)]))->assertNotFound();
    })->with(['de.bs.en', 'en', 'he.bs.en', 'BS.en']);

    it('changes the version when a string changes', function () {
        $root = useStringFixtures(['bs', 'en'], ['en' => ['a' => 'one'], 'bs' => ['a' => 'jedan']]);
        $before = strings()->bundleVersion(['bs', 'en']);

        file_put_contents("{$root}/bs/core.json", (string) json_encode(['a' => 'jedan!']));
        app()->instance(StringCatalog::class, new StringCatalog($root, app(ModuleRegistry::class), "{$root}/cache", new NullLogger));
        app()->forgetScopedInstances();

        expect(strings()->bundleVersion(['bs', 'en']))->not->toBe($before);
    });
});

describe('compiled strings', function () {
    it('compiles per locale and reads the compiled file until it is cleared', function () {
        $root = useStringFixtures(['bs', 'en'], ['en' => ['a' => 'one'], 'bs' => ['a' => 'jedan']]);
        // Shipped modules bring texts of their own; only the fixtures count here.
        $noModules = new ModuleRegistry([], new NullLogger);
        $catalog = new StringCatalog($root, $noModules, "{$root}/cache", new NullLogger);

        expect($catalog->compile(['bs', 'en']))->toBe(['bs' => 1, 'en' => 1])
            ->and("{$root}/cache/bs.php")->toBeFile();

        file_put_contents("{$root}/bs/core.json", (string) json_encode(['a' => 'promijenjeno']));
        $fresh = new StringCatalog($root, app(ModuleRegistry::class), "{$root}/cache", new NullLogger);
        expect($fresh->message('bs', 'core:a'))->toBe('jedan');

        $fresh->clear();
        expect($fresh->message('bs', 'core:a'))->toBe('promijenjeno')
            ->and("{$root}/cache/bs.php")->not->toBeFile();
    });
});

describe('shipped strings', function () {
    it('ships German for every identifier, each valid ICU', function () {
        $catalog = app(StringCatalog::class);
        $de = $catalog->messages('de');
        expect(array_keys($de))->toEqualCanonicalizing(array_keys($catalog->messages('en')));
        foreach ($de as $message) {
            expect(new MessageFormatter('de', $message))->toBeInstanceOf(MessageFormatter::class);
        }
    });

    it('has the same identifiers in en and bs, each valid ICU', function () {
        $catalog = app(StringCatalog::class);
        $en = $catalog->messages('en');
        $bs = $catalog->messages('bs');

        expect(array_keys($bs))->toEqualCanonicalizing(array_keys($en));

        foreach (['en' => $en, 'bs' => $bs] as $locale => $messages) {
            foreach ($messages as $identifier => $message) {
                try {
                    new MessageFormatter($locale, $message);
                } catch (IntlException $e) {
                    $this->fail("{$locale} {$identifier}: {$e->getMessage()}");
                }
            }
        }
    });
});

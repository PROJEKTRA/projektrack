<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\I18n\LanguageChangeRefused;
use App\I18n\MessageArguments;
use App\I18n\StringCatalog;
use App\I18n\Strings;
use App\I18n\TenantLanguages;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\StringOverride;
use App\Models\Tenant;
use App\Models\TenantLanguage;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;

/*
 * S-0921: a tenant deactivates languages and copies a language into its own
 * one, which it localizes by export and import.
 */

function languageAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());

    return $admin;
}

function languages(): TenantLanguages
{
    app()->forgetScopedInstances();

    return app(TenantLanguages::class);
}

/** Every en identifier of the shipped strings. */
function sourceIds(): array
{
    return array_keys(app(StringCatalog::class)->messages('en'));
}

describe('deactivating a language', function () {
    it('stops offering it, shows its users the organization language and keeps their choice', function () {
        $admin = languageAdmin();
        $ana = User::factory()->create(['locale' => 'en']);

        $this->actingAs($admin)->patch('/admin/languages/en', ['active' => false])
            ->assertRedirect('/admin/languages');

        expect(languages()->locales()->available())->not->toContain('en')
            ->and($ana->fresh()?->locale)->toBe('en');

        $this->actingAs($ana)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('strings.locale', 'bs'));

        $this->actingAs($ana)->get('/settings/profile')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('locales', fn ($options) => collect($options)->pluck('value')->doesntContain('en')));

        $this->actingAs($ana)->patch('/settings/profile', ['name' => $ana->name, 'email' => $ana->email, 'locale' => 'en'])
            ->assertSessionHasErrors('locale');

        $entry = AuditEntry::query()->where('action', 'languages.deactivated')->latest('seq')->first();
        expect($entry?->contextData())->toBe(['locale' => 'en', 'users' => 1]);

        $this->actingAs($admin)->patch('/admin/languages/en', ['active' => true])->assertSessionHasNoErrors();
        $this->actingAs($ana)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('strings.locale', 'en'));
    });

    it('keeps en as the last fallback of every chain while deactivated', function () {
        languages()->setActive('en', false, languageAdmin());

        expect(app(Strings::class)->chain('bs'))->toBe(['bs', 'en']);
    });

    it('refuses to deactivate the organization language', function () {
        $this->actingAs(languageAdmin())->patch('/admin/languages/bs', ['active' => false])
            ->assertSessionHasErrors('active');

        expect(languages()->locales()->available())->toContain('bs');
    });

    it('is only for users allowed to customize strings', function () {
        $employee = User::factory()->create();
        app(RoleManager::class)->assign($employee, Role::query()->where('key', 'employee')->firstOrFail());

        $this->actingAs($employee)->get('/admin/languages')->assertForbidden();
        $this->actingAs($employee)->patch('/admin/languages/en', ['active' => false])->assertForbidden();
        $this->actingAs($employee)->post('/admin/languages', ['from' => 'bs', 'locale' => 'hr', 'name' => 'Hrvatski', 'direction' => 'ltr'])->assertForbidden();

        expect(TenantLanguage::query()->count())->toBe(0);
    });
});

describe('copying a language', function () {
    it('creates an own language with every string of the source, marked for translation', function () {
        $admin = languageAdmin();

        $this->actingAs($admin)
            ->post('/admin/languages', ['from' => 'bs', 'locale' => 'HR', 'name' => 'Hrvatski', 'direction' => 'ltr'])
            ->assertRedirect('/admin/languages')
            ->assertSessionHasNoErrors();

        $catalog = app(StringCatalog::class);
        $copied = StringOverride::query()->where('locale', 'hr')->get();

        expect($copied->pluck('identifier')->sort()->values()->all())->toBe(collect(sourceIds())->sort()->values()->all())
            ->and($copied->every(fn (StringOverride $o): bool => $o->needs_review))->toBeTrue()
            ->and($copied->firstWhere('identifier', 'core:common.save')?->value)->toBe($catalog->message('bs', 'core:common.save'));

        $hr = collect(languages()->describe())->firstWhere('locale', 'hr');
        expect($hr)->toMatchArray(['name' => 'Hrvatski', 'kind' => 'custom', 'based_on' => 'bs', 'active' => true, 'completeness' => 0])
            ->and(AuditEntry::query()->where('action', 'languages.copied')->latest('seq')->first()?->contextData())
            ->toBe(['based_on' => 'bs', 'locale' => 'hr', 'strings' => count(sourceIds())]);
    });

    it('lets users choose the copy and falls back to the source for strings added later', function () {
        languages()->copy('bs', 'hr', 'Hrvatski', 'ltr', languageAdmin());
        StringOverride::query()->where('locale', 'hr')->where('identifier', 'core:common.cancel')->delete();
        TenantLanguage::query()->where('locale', 'hr')->increment('revision');

        $ivo = User::factory()->create();
        $this->actingAs($ivo)->patch('/settings/profile', ['name' => $ivo->name, 'email' => $ivo->email, 'locale' => 'hr'])
            ->assertSessionHasNoErrors();

        app()->forgetScopedInstances();
        $strings = app(Strings::class);

        expect($strings->chain('hr'))->toBe(['hr', 'bs', 'en'])
            ->and($strings->get('core:common.cancel', [], 'hr'))->toBe($strings->get('core:common.cancel', [], 'bs'));

        $this->actingAs($ivo->fresh())->get('/dashboard')
            ->assertSee('lang="hr" dir="ltr"', false)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('strings.locale', 'hr'));
    });

    it('formats a copy with the plural rules of its own language', function () {
        languages()->copy('en', 'ru', 'Русский', 'ltr', languageAdmin());
        languages()->import('ru', ['core:two_factor.recovery_codes_hidden' => '{count, plural, one {# код} few {# кода} many {# кодов} other {# кода}}'], languageAdmin());

        app()->forgetScopedInstances();

        expect(app(Strings::class)->get('core:two_factor.recovery_codes_hidden', ['count' => 5], 'ru'))->toBe('5 кодов');
    });

    it('marks a right-to-left copy as such', function () {
        languages()->copy('en', 'ur', 'اردو', 'rtl', languageAdmin());

        expect(languages()->locales()->direction('ur'))->toBe('rtl');
    });

    it('refuses codes that are malformed, refused, unknown or taken', function (string $locale, string $reason) {
        $this->actingAs(languageAdmin())
            ->post('/admin/languages', ['from' => 'bs', 'locale' => $locale, 'name' => 'Test', 'direction' => 'ltr'])
            ->assertSessionHasErrors(['locale' => app(Strings::class)->get($reason)]);

        expect(TenantLanguage::query()->count())->toBe(0);
    })->with([
        ['croatian', 'core:languages.error_code_format'],
        ['he', 'core:languages.error_code_refused'],
        ['iw-IL', 'core:languages.error_code_refused'],
        ['qq', 'core:languages.error_code_unknown'],
        ['de-QQ', 'core:languages.error_code_unknown'],
        ['en', 'core:languages.error_code_exists'],
    ]);

    it('refuses a pseudo-locale or an unknown language as source', function () {
        expect(fn () => languages()->copy('xx', 'hr', 'Hrvatski', 'ltr', languageAdmin()))->toThrow(LanguageChangeRefused::class)
            ->and(fn () => languages()->copy('he', 'hr', 'Hrvatski', 'ltr', languageAdmin()))->toThrow(LanguageChangeRefused::class);
    });
});

describe('localizing an own language', function () {
    beforeEach(function () {
        $this->admin = languageAdmin();
        languages()->copy('bs', 'hr', 'Hrvatski', 'ltr', $this->admin);
    });

    it('exports the strings with the en source as a JSON download', function () {
        $response = $this->actingAs($this->admin)->get('/admin/languages/hr/export')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="strings-hr.json"');

        $export = $response->json();
        expect($export['format'])->toBe(TenantLanguages::FORMAT)
            ->and($export['locale'])->toBe('hr')
            ->and($export['based_on'])->toBe('bs')
            ->and(array_keys($export['messages']))->toEqualCanonicalizing(sourceIds())
            ->and($export['source']['core:common.save'])->toBe('Save');
    });

    it('imports an exported file, counts the strings as translated and renews the bundle', function () {
        $before = app(Strings::class)->bundleVersion(['hr', 'bs', 'en']);
        $export = languages()->export('hr');
        $export['messages'] = ['core:common.save' => 'Spremi', 'core:common.cancel' => 'Odustani'];

        $this->actingAs($this->admin)
            ->post('/admin/languages/hr/import', ['file' => UploadedFile::fake()->createWithContent('strings-hr.json', (string) json_encode($export))])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/languages');

        app()->forgetScopedInstances();
        $strings = app(Strings::class);

        expect($strings->get('core:common.save', [], 'hr'))->toBe('Spremi')
            ->and($strings->bundle(['hr', 'bs', 'en'])['messages']['core:common.save'])->toBe('Spremi')
            ->and($strings->bundleVersion(['hr', 'bs', 'en']))->not->toBe($before)
            ->and(collect(languages()->describe())->firstWhere('locale', 'hr')['completeness'])->toBe((int) floor(200 / count(sourceIds())))
            ->and(StringOverride::query()->where('identifier', 'core:common.save')->where('locale', 'hr')->value('needs_review'))->toBeFalsy();
    });

    it('accepts a plain identifier to message object', function () {
        expect(languages()->import('hr', ['core:common.save' => 'Spremi'], $this->admin))->toBe(1);
    });

    it('refuses the whole file when one message is invalid and lists every reason', function () {
        $file = UploadedFile::fake()->createWithContent('strings-hr.json', (string) json_encode([
            'core:common.save' => 'Spremi',
            'core:common.cancel' => '{broken',
            'core:not_a_string' => 'x',
            'core:two_factor.recovery_codes_hidden' => 'Imate {total} kodova',
        ]));

        $response = $this->actingAs($this->admin)->post('/admin/languages/hr/import', ['file' => $file])
            ->assertSessionHasErrors('file');

        $message = session('errors')->first('file');
        expect($message)->toContain('core:common.cancel')
            ->toContain('core:not_a_string')
            ->toContain('total')
            ->and(StringOverride::query()->where('locale', 'hr')->where('identifier', 'core:common.save')->value('value'))
            ->toBe(app(StringCatalog::class)->message('bs', 'core:common.save'));
    });

    it('refuses an import into an included language', function () {
        expect(fn () => languages()->import('en', ['core:common.save' => 'Store'], $this->admin))
            ->toThrow(LanguageChangeRefused::class);
    });

    it('refuses a file that is not JSON', function () {
        $this->actingAs($this->admin)
            ->post('/admin/languages/hr/import', ['file' => UploadedFile::fake()->createWithContent('strings.json', 'not json')])
            ->assertSessionHasErrors('file');
    });
});

describe('tenant isolation', function () {
    it('keeps a copied language and deactivations inside their tenant', function () {
        languages()->copy('bs', 'hr', 'Hrvatski', 'ltr', languageAdmin());
        languages()->setActive('en', false, languageAdmin());

        $other = Tenant::factory()->withDomain('other.test')->create();
        $context = app(TenantContext::class);

        $context->run($other, function () {
            $languages = languages();

            expect($languages->locales()->isKnown('hr'))->toBeFalse()
                ->and($languages->locales()->available())->toContain('en')
                ->and(app(Strings::class)->chain('hr'))->toBe(['bs', 'en'])
                ->and(StringOverride::query()->count())->toBe(0);
        });

        $version = app(Strings::class)->bundleVersion(['hr', 'bs', 'en']);
        $this->get("http://localhost/strings/hr.bs.en/{$version}.json")->assertOk();
        $this->get("http://other.test/strings/hr.bs.en/{$version}.json")->assertNotFound();
    });
});

describe('message arguments', function () {
    it('finds :expected in :message', function (string $message, array $expected) {
        expect(MessageArguments::of($message))->toBe($expected);
    })->with([
        ['Hello {name}', ['name']],
        ['{count, plural, one {# day} other {# days in {month}}}', ['count', 'month']],
        ['{gender, select, female {She} other {They}} paid {amount, number, ::currency/EUR}', ['amount', 'gender']],
        ["It''s '{literal}' and {real}", ['real']],
        ['{n, selectordinal, offset:1 one {#st} other {#th}}', ['n']],
        ['{when, date, short} {brand}', ['brand', 'when']],
        ['No arguments', []],
    ]);
});

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\I18n\LanguageChangeRefused;
use App\I18n\StringCatalog;
use App\I18n\StringEditor;
use App\I18n\Strings;
use App\I18n\TenantLanguages;
use App\Models\AuditEntry;
use App\Models\Role;
use App\Models\StringOverride;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Notifications\TwoFactorWasReset;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia;
use Psr\Log\NullLogger;

/*
 * S-0918: a tenant admin replaces any string in any language with own text.
 */

function stringAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());

    return $admin;
}

function freshStrings(): Strings
{
    app()->forgetScopedInstances();

    return app(Strings::class);
}

describe('overriding a string', function () {
    it('shows the tenant text on every screen, in e-mails and in the browser bundle', function () {
        $admin = stringAdmin();

        $this->actingAs($admin)
            ->put('/admin/languages/en/strings', ['identifier' => 'core:common.save', 'value' => 'Store'])
            ->assertSessionHasNoErrors();

        $strings = freshStrings();
        expect($strings->get('core:common.save', [], 'en'))->toBe('Store')
            ->and($strings->bundle(['en', 'bs'])['messages']['core:common.save'])->toBe('Store');

        App::setLocale('en');
        expect(__('core:common.save'))->toBe('Store');

        $override = StringOverride::query()->firstOrFail();
        expect($override->needs_review)->toBeFalse()
            ->and($override->source_hash)->toBe(TenantLanguages::sourceHash('Save'))
            ->and(AuditEntry::query()->where('action', 'strings.overridden')->latest('seq')->first()?->contextData())
            ->toBe(['identifier' => 'core:common.save', 'locale' => 'en']);
    });

    it('applies to e-mails and renders HTML in a text as text', function () {
        app(StringEditor::class)->save('bs', 'core:two_factor.reset_mail.body', 'Vaš <b>drugi faktor</b> je uklonjen.', stringAdmin());
        $ana = User::factory()->create(['locale' => 'bs']);

        $html = (string) (new TwoFactorWasReset)->toMail($ana)->render();

        expect($html)->toContain('Vaš &lt;b&gt;drugi faktor&lt;/b&gt; je uklonjen.')
            ->not->toContain('<b>drugi faktor</b>');
    });

    it('changes the bundle version so browsers load the new text', function () {
        $before = freshStrings()->bundleVersion(['bs', 'en']);
        app(StringEditor::class)->save('bs', 'core:common.save', 'Spremi', stringAdmin());

        expect(freshStrings()->bundleVersion(['bs', 'en']))->not->toBe($before);
    });

    it('restores the shipped text when the override is removed', function () {
        $admin = stringAdmin();
        app(StringEditor::class)->save('bs', 'core:common.save', 'Spremi', $admin);

        $this->actingAs($admin)
            ->delete('/admin/languages/bs/strings', ['identifier' => 'core:common.save'])
            ->assertSessionHasNoErrors();

        expect(freshStrings()->get('core:common.save', [], 'bs'))->toBe(app(StringCatalog::class)->message('bs', 'core:common.save'))
            ->and(StringOverride::query()->count())->toBe(0)
            ->and(AuditEntry::query()->where('action', 'strings.reset')->exists())->toBeTrue();
    });

    it('refuses invalid ICU and placeholders the original does not have', function (string $value) {
        $this->actingAs(stringAdmin())
            ->put('/admin/languages/bs/strings', ['identifier' => 'core:two_factor.recovery_codes_hidden', 'value' => $value])
            ->assertSessionHasErrors('value');

        expect(StringOverride::query()->count())->toBe(0);
    })->with([
        'broken syntax' => ['Imate {count, plural, one {# kod}'],
        'unknown placeholder' => ['Imate {total} kodova'],
    ]);

    it('refuses unknown identifiers and pseudo-locales', function () {
        $admin = stringAdmin();

        $this->actingAs($admin)->put('/admin/languages/bs/strings', ['identifier' => 'core:no_such.string', 'value' => 'x'])
            ->assertSessionHasErrors('value');
        expect(fn () => app(StringEditor::class)->save('xx', 'core:common.save', 'x', $admin))->toThrow(LanguageChangeRefused::class);
    });

    it('is only for users allowed to customize strings', function () {
        $employee = User::factory()->create();
        app(RoleManager::class)->assign($employee, Role::query()->where('key', 'employee')->firstOrFail());

        $this->actingAs($employee)->get('/admin/languages/bs/strings')->assertForbidden();
        $this->actingAs($employee)->put('/admin/languages/bs/strings', ['identifier' => 'core:common.save', 'value' => 'x'])->assertForbidden();
        $this->actingAs($employee)->delete('/admin/languages/bs/strings', ['identifier' => 'core:common.save'])->assertForbidden();
    });

    it('keeps the override inside its tenant', function () {
        app(StringEditor::class)->save('bs', 'core:common.save', 'Spremi', stringAdmin());

        app(TenantContext::class)->run(Tenant::factory()->create(), function () {
            expect(freshStrings()->get('core:common.save', [], 'bs'))->toBe(app(StringCatalog::class)->message('bs', 'core:common.save'));
        });
    });

    it('needs the legal notice permission for legal notices', function () {
        $root = sys_get_temp_dir().'/ptk-legal-'.bin2hex(random_bytes(6));
        foreach (['en' => 'Reverse charge', 'bs' => 'Prijenos porezne obaveze'] as $locale => $text) {
            mkdir("{$root}/{$locale}", recursive: true);
            file_put_contents("{$root}/{$locale}/core.json", (string) json_encode(['invoice.legal_notice_reverse_charge' => $text, 'common.save' => 'Save']));
        }
        app()->instance(StringCatalog::class, new StringCatalog($root, app(ModuleRegistry::class), "{$root}/cache", new NullLogger));
        app()->forgetScopedInstances();

        $editor = app(StringEditor::class);
        $result = $editor->search('bs', 'reverse');

        expect($result['rows'][0]['protected'])->toBeTrue()
            ->and(fn () => $editor->save('bs', 'core:invoice.legal_notice_reverse_charge', 'Obrnuta obaveza', stringAdmin()))
            ->toThrow(LanguageChangeRefused::class);
    });
});

describe('finding strings', function () {
    it('searches by identifier or text, filters by module and shows source, original and override side by side', function () {
        $admin = stringAdmin();
        app(StringEditor::class)->save('bs', 'core:common.save', 'Spremi', $admin);

        $this->actingAs($admin)->get('/admin/languages/bs/strings?q=spremi&component=core')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Strings')
                ->where('language.locale', 'bs')
                ->where('total', 1)
                ->where('rows.0.identifier', 'core:common.save')
                ->where('rows.0.source', 'Save')
                ->where('rows.0.original', app(StringCatalog::class)->message('bs', 'core:common.save'))
                ->where('rows.0.override', 'Spremi')
                ->where('components', fn ($components) => collect($components)->contains('core')));

        $byId = app(StringEditor::class)->search('bs', 'common.save');
        expect(collect($byId['rows'])->pluck('identifier'))->toContain('core:common.save');

        $customized = app(StringEditor::class)->search('bs', '', '', 'customized');
        expect($customized['total'])->toBe(1);
    });

    it('keeps an override when the en source changes in an update and flags it for review', function () {
        app(StringEditor::class)->save('bs', 'core:common.save', 'Spremi', stringAdmin());
        // As if an update changed the en text after the override was written.
        StringOverride::query()->update(['source_hash' => TenantLanguages::sourceHash('Save now')]);

        $changed = app(StringEditor::class)->search('bs', '', '', 'source_changed');

        expect($changed['total'])->toBe(1)
            ->and($changed['rows'][0]['source_changed'])->toBeTrue()
            ->and(freshStrings()->get('core:common.save', [], 'bs'))->toBe('Spremi');

        // Saving again confirms the text against the current source.
        app(StringEditor::class)->save('bs', 'core:common.save', 'Spremi', stringAdmin());
        expect(app(StringEditor::class)->search('bs', '', '', 'source_changed')['total'])->toBe(0);
    });

    it('lists the untranslated strings of a copied language and shows the parent text as original', function () {
        $admin = stringAdmin();
        app(TenantLanguages::class)->copy('bs', 'hr', 'Hrvatski', 'ltr', $admin);
        app()->forgetScopedInstances();

        $editor = app(StringEditor::class);
        $total = count(app(StringCatalog::class)->messages('en'));
        expect($editor->search('hr', '', '', 'untranslated')['total'])->toBe($total);

        $editor->save('hr', 'core:common.save', 'Spremi', $admin);
        $row = collect($editor->search('hr', 'common.save')['rows'])->firstWhere('identifier', 'core:common.save');

        expect($editor->search('hr', '', '', 'untranslated')['total'])->toBe($total - 1)
            ->and($row['original'])->toBe(app(StringCatalog::class)->message('bs', 'core:common.save'))
            ->and($row['override'])->toBe('Spremi');
    });

    it('pages through long lists', function () {
        $result = app(StringEditor::class)->search('bs', '', '', 'all', 2);

        expect($result['page'])->toBe(2)
            ->and(count($result['rows']))->toBeLessThanOrEqual(StringEditor::PER_PAGE)
            ->and($result['pages'])->toBe((int) ceil($result['total'] / StringEditor::PER_PAGE));
    });
});

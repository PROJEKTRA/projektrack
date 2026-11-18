<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\I18n\Strings;
use App\I18n\TenantLanguages;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * S-0920: every page carries lang and dir of the user's language.
 */

function arabicLanguage(): void
{
    $admin = User::factory()->withTwoFactor()->create();
    app(RoleManager::class)->assign($admin, Role::query()->where('key', 'admin')->firstOrFail());
    // Arabic arrives with the language packs; until then a tenant copy stands in.
    app(TenantLanguages::class)->copy('en', 'ar', 'Arabic', 'rtl', $admin);
    app()->forgetScopedInstances();
}

it('sets html lang and dir from the language of the user', function (string $locale, string $dir) {
    if ($locale === 'ar') {
        arabicLanguage();
    }
    $user = User::factory()->create(['locale' => $locale]);

    $response = $this->actingAs($user)->get('/dashboard')->assertOk();

    expect($response->getContent())
        ->toContain("<html lang=\"{$locale}\" dir=\"{$dir}\"")
        ->toContain("data-locale=\"{$locale}\" data-dir=\"{$dir}\"");
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('strings.locale', $locale)
        ->where('strings.dir', $dir));
})->with([
    'Bosnian' => ['bs', 'ltr'],
    'English' => ['en', 'ltr'],
    'Arabic' => ['ar', 'rtl'],
    'pseudo right to left' => ['xx-rtl', 'rtl'],
]);

it('sets lang and dir on pages before sign-in from the tenant language', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<html lang="bs" dir="ltr"', false);
});

it('isolates arguments in right to left messages so keys and amounts keep their order', function () {
    arabicLanguage();

    $text = app(Strings::class)->get('core:strings.title', ['name' => 'PTK-123'], 'ar');

    expect($text)->toContain("\u{2068}PTK-123\u{2069}");
});

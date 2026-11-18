<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\I18n\Formats;
use App\I18n\RegionalFormat;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * S-0919: locale formats and time zones. The fixture is shared with
 * resources/js/i18n/formats.test.ts, so server and browser agree.
 */

/** ICU versions differ in narrow and normal no-break spaces. */
function sameSpaces(string $text): string
{
    return (string) preg_replace('/[\s\x{00a0}\x{202f}]+/u', ' ', $text);
}

describe('formatting like the browser', function () {
    $fixture = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/i18n/formats.json'), true);

    foreach ($fixture['cases'] as $case) {
        it($case['name'], function () use ($case) {
            $format = new RegionalFormat($case['locale'], $case['timeZone'], $case['pattern']);
            $at = fn (): DateTimeImmutable => new DateTimeImmutable($case['input']);

            $result = match ($case['fn']) {
                'date' => $format->date($at()),
                'day' => $format->day($case['input']),
                'time' => $format->time($at()),
                'dateTime' => $format->dateTime($at()),
                'number' => $format->number($case['input'], $case['decimals'] ?? 0),
                'money' => $format->money($case['input'], $case['currency']),
            };

            expect(sameSpaces($result))->toBe(sameSpaces($case['expect']));
        });
    }
});

it('refuses amounts it cannot show exactly', function () {
    expect(fn () => (new RegionalFormat('bs-BA', 'UTC'))->money(RegionalFormat::EXACT_LIMIT, 'EUR'))
        ->toThrow(InvalidArgumentException::class);
});

describe('choosing the format and time zone', function () {
    it('uses the usual format of the language and the time zone of the tenant by default', function () {
        $this->tenant->update(['timezone' => 'Europe/Sarajevo']);
        $ana = User::factory()->create(['locale' => 'bs']);

        $format = app(Formats::class)->for($ana);

        expect($format->locale)->toBe('bs-BA')
            ->and($format->timezone)->toBe('Europe/Sarajevo')
            ->and($format->date(new DateTimeImmutable('2026-11-01T10:00:00Z')))->toBe('01.11.2026')
            ->and(sameSpaces($format->money(123450, 'EUR')))->toBe('1.234,50 EUR');
    });

    it('lets a user choose English interface with German formats and an own time zone', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'en',
                'format_locale' => 'de-DE',
                'timezone' => 'America/New_York',
            ])
            ->assertSessionHasNoErrors();

        $format = app(Formats::class)->for($user->refresh());

        expect(app(Formats::class)->for($user)->locale)->toBe('de-DE')
            ->and($format->timezone)->toBe('America/New_York')
            ->and($format->dateTime(new DateTimeImmutable('2026-11-01T14:05:00Z')))->toBe('01.11.2026 09:05');

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('formats.locale', 'de-DE')
                ->where('formats.timeZone', 'America/New_York')
                ->where('formats.datePattern', null));
    });

    it('refuses formats that are not offered and unknown time zones', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'format_locale' => 'tlh-QO',
                'timezone' => 'Mars/Olympus_Mons',
            ])
            ->assertSessionHasErrors(['format_locale', 'timezone']);
    });

    it('offers each format under its name in the interface language with an example', function () {
        $options = collect(app(Formats::class)->options('bs'));
        $german = $options->firstWhere('value', 'de-DE');

        expect($options->pluck('value')->sort()->values()->all())->toBe(collect(Formats::offered())->sort()->values()->all())
            ->and($german['label'])->toBe(Locale::getDisplayName('de-DE', 'bs'))
            ->and(sameSpaces($german['example']))->toBe('01.11.2026, 1.234,50');
    });

    it('shows the profile with the choices and the tenant time zone', function () {
        $this->actingAs(User::factory()->create())
            ->get('/settings/profile')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('formatLocale', null)
                ->where('timezone', null)
                ->where('tenantTimezone', $this->tenant->timezone)
                ->has('formats', count(Formats::offered()))
                ->where('timezones', fn ($zones) => collect($zones)->contains('value', 'Europe/Sarajevo')));
    });
});

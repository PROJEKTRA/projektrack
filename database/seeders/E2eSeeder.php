<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Database\Seeders;

use App\Authorization\RoleManager;
use App\I18n\TenantLanguages;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Settings\Settings;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Modules\Crm\Domain\Companies;
use RuntimeException;

/**
 * Synthetic data for the browser tests (tests/e2e, S-0920): one tenant at the
 * host of APP_URL, one administrator per test language and an Arabic tenant
 * language standing in for the Arabic language pack. Never run in production.
 *
 *   php artisan migrate:fresh --seeder=E2eSeeder
 */
class E2eSeeder extends Seeder
{
    /** Password of every test user (synthetic, tests/e2e/support.ts). */
    public const string PASSWORD = 'e2e-password-not-secret';

    /**
     * Test users by language: e-mail and base32 TOTP secret (synthetic,
     * tests/e2e/support.ts). One secret per user, because a code is accepted
     * only once.
     *
     * The crawl (S-0988) signs in as its own user, in parallel with the others.
     *
     * @var array<string, array{email: string, secret: string, locale: string}>
     */
    public const array USERS = [
        'bs' => ['email' => 'e2e-bs@example.test', 'secret' => 'JBSWY3DPEHPK3PXP', 'locale' => 'bs'],
        'ar' => ['email' => 'e2e-ar@example.test', 'secret' => 'GEZDGNBVGY3TQOJQ', 'locale' => 'ar'],
        'crawl' => ['email' => 'e2e-crawl@example.test', 'secret' => 'KRSXG5DDOJQXOZBA', 'locale' => 'bs'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The browser test seeder must not run in production.');
        }

        $url = config('app.url');
        $host = (is_string($url) ? parse_url($url, PHP_URL_HOST) : null) ?: 'localhost';

        $tenant = Tenant::factory()->withDomain($host)->create([
            'slug' => 'e2e-company',
            'name' => 'E2E Company d.o.o.',
        ]);

        app(TenantContext::class)->run($tenant, function (): void {
            $admin = Role::query()->where('key', Role::ADMIN)->firstOrFail();
            $users = [];
            app(TenantModules::class)->enable('service_reports');
            app(TenantModules::class)->enable('invoices');
            app(TenantContext::class)->current()->update(['country_code' => 'BA', 'base_currency' => 'BAM']);
            app(Settings::class)->save('invoices', ['legal_name' => 'E2E Company d.o.o.', 'street' => 'Testna 2', 'postal_code' => '72270', 'city' => 'Travnik', 'tax_number' => '4000000000000', 'vat_id' => null, 'vat_status' => 'registered', 'bank' => null, 'iban' => null, 'bic' => null, 'contact' => null, 'legal_texts_confirmed' => true], User::factory()->create(['email' => 'e2e-settings@example.test']));
            app(Companies::class)->create(['company' => ['name' => 'E2E Customer GmbH', 'kind' => 'customer', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'pdf'], 'tax' => ['tax_status' => 'domestic', 'country_code' => 'BA'], 'address' => ['line1' => 'Testna 1', 'city' => 'Travnik', 'country_code' => 'BA']], User::factory()->create(['email' => 'e2e-seed@example.test']));

            foreach (self::USERS as $key => $user) {
                $users[$key] = User::factory()->create([
                    'name' => "E2E {$key}",
                    'email' => $user['email'],
                    'password' => self::PASSWORD,
                    'locale' => $user['locale'],
                    'two_factor_secret' => encrypt($user['secret']),
                    'two_factor_recovery_codes' => json_encode([]),
                    'two_factor_confirmed_at' => now(),
                ]);
                app(RoleManager::class)->assign($users[$key], $admin);
            }

            app(TenantLanguages::class)->copy('en', 'ar', 'العربية', 'rtl', $users['bs']);
        });
    }
}

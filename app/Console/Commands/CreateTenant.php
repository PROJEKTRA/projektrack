<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Console\Commands;

use App\Audit\AuditLog;
use App\Authorization\RoleManager;
use App\I18n\Locales;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates a tenant with its address and first administrator on a running
 * instance (S-0923, delivery runbook). The administrator gets a one-time
 * setup link by mail and sets their own password and second factor: the
 * operator never knows the password and the link is never printed.
 */
class CreateTenant extends Command
{
    protected $signature = 'projektrack:tenant:create
        {--slug= : Short technical name, for example "acme"}
        {--name= : Company name shown to users}
        {--domain= : Host name the tenant is served at, for example app.example.com}
        {--admin-email= : Address of the first administrator; the setup link goes there}
        {--admin-name= : Name of the first administrator}
        {--locale=bs : Default language}
        {--country=BA : Country code (ISO 3166-1 alpha-2)}
        {--currency=BAM : Base currency (ISO 4217)}
        {--timezone=Europe/Sarajevo : Time zone}';

    protected $description = 'Create a tenant with its address and first administrator, who gets a setup link by mail';

    public function handle(TenantContext $context, Locales $locales, RoleManager $roles, AuditLog $audit): int
    {
        $input = [
            'slug' => $this->option('slug'),
            'name' => $this->option('name'),
            'domain' => is_string($this->option('domain')) ? strtolower(rtrim($this->option('domain'), '.')) : null,
            'admin_email' => $this->option('admin-email'),
            'admin_name' => $this->option('admin-name'),
            'locale' => $this->option('locale'),
            'country' => $this->option('country'),
            'currency' => $this->option('currency'),
            'timezone' => $this->option('timezone'),
        ];

        $validator = Validator::make($input, [
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:200'],
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/'],
            'admin_email' => ['required', 'string', 'max:254', 'email:rfc'],
            'admin_name' => ['required', 'string', 'max:200'],
            'locale' => ['required', 'string', fn (string $attribute, mixed $value, \Closure $fail) => $locales->isAvailable(is_string($value) ? $value : null) || $fail('Unknown or unavailable locale.')],
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        /** @var array{slug: string, name: string, domain: string, admin_email: string, admin_name: string, locale: string, country: string, currency: string, timezone: string} $data */
        $data = $validator->validated();

        if (Tenant::query()->where('slug', $data['slug'])->exists()) {
            $this->error('A tenant with this slug exists already.');

            return self::FAILURE;
        }

        if (TenantDomain::withoutGlobalScope(TenantScope::class)->where('host', $data['domain'])->exists()) {
            $this->error('This host name is in use already.');

            return self::FAILURE;
        }

        $tenant = DB::transaction(function () use ($data, $context, $roles, $audit): Tenant {
            $tenant = Tenant::query()->create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'status' => 'active',
                'country_code' => $data['country'],
                'default_locale' => Locales::normalize($data['locale']),
                'base_currency' => $data['currency'],
                'timezone' => $data['timezone'],
                'region' => 'eu',
                'edition' => 'community',
            ]);

            $context->run($tenant, function () use ($data, $roles, $audit): void {
                TenantDomain::query()->create([
                    'host' => $data['domain'],
                    'kind' => 'custom',
                    'is_primary' => true,
                    'verified_at' => now(),
                ]);

                // No usable password: the administrator sets one through the setup
                // link, which only reaches this address, so the address counts as verified.
                $admin = User::query()->forceCreate([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'password' => Hash::make(Str::random(64)),
                    'email_verified_at' => now(),
                ]);

                $roles->assign($admin, Role::query()->where('key', Role::ADMIN)->firstOrFail());
                $audit->record('tenant.created', $admin, ['by' => 'console']);
            });

            return $tenant;
        });

        $status = $context->run($tenant, fn (): string => Password::broker()->sendResetLink(['email' => $data['admin_email']]));

        $this->info("Tenant {$tenant->slug} created at {$data['domain']}.");

        if ($status !== Password::RESET_LINK_SENT) {
            $this->warn('The setup link could not be sent. The administrator can use "Forgot password" on the sign-in page.');

            return self::FAILURE;
        }

        $this->line('A setup link was sent to the administrator. It is valid for '.config()->integer('auth.passwords.users.expire', 60).' minutes.');

        return self::SUCCESS;
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0939: customers with tax profile, addresses, sites and contacts (data
 * model section 5). Foreign keys between them carry the tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('number', 20);
            $table->string('name', 191);
            $table->string('legal_name', 191)->nullable();
            $table->string('kind', 32);
            $table->string('status', 32);
            $table->string('document_locale', 12);
            $table->char('currency', 3);
            $table->unsignedSmallInteger('payment_days');
            $table->string('einvoice_format', 32);
            $table->string('leitweg_id', 46)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('company_tax_profiles', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofCompany($table);
            $table->string('tax_status', 32);
            $table->char('country_code', 2);
            $table->string('vat_id', 20)->nullable();
            $table->string('tax_number', 40)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'company_id', 'valid_from'], 'company_tax_profiles_period');
        });

        Schema::create('company_sites', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofCompany($table);
            $table->string('name', 191);
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('owner_type', 32);
            $table->char('owner_id', 26);
            $table->string('kind', 32);
            $table->string('line1', 191);
            $table->string('line2', 191)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 120);
            $table->string('region', 120)->nullable();
            $table->char('country_code', 2);
            $table->timestamps();

            $table->unique(['tenant_id', 'owner_type', 'owner_id', 'kind'], 'addresses_owner_unique');
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofCompany($table);
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120);
            $table->string('email_normalized', 191)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('position', 120)->nullable();
            $table->boolean('invoice_recipient')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'company_id', 'email_normalized'], 'contacts_email_unique');
        });
    }

    public function down(): void
    {
        foreach (['contacts', 'addresses', 'company_sites', 'company_tax_profiles', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }

    private function ofCompany(Blueprint $table): void
    {
        $table->foreignUlid('company_id');
        $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->cascadeOnDelete();
    }
};

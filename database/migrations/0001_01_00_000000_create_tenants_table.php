<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tenancy core (ADR-0006, data model section 2). Tenants are instance-scoped;
 * tenant_domains maps request hosts to tenants and is read before any tenant
 * context exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('status', 16)->default('active');
            $table->char('country_code', 2);
            $table->string('default_locale', 16)->default('bs');
            $table->char('base_currency', 3);
            $table->string('timezone', 64);
            $table->string('region', 16)->default('eu');
            $table->string('isolation_mode', 16)->default('shared');
            $table->string('edition', 16)->default('community');
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('host')->unique();
            $table->string('kind', 16)->default('custom');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};

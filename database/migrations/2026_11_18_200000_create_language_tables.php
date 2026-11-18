<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Languages per tenant and string overrides (S-0921, S-0918, ADR-0005 sections 4 and 5).
 *
 * tenant_languages holds a row only where a tenant differs from the shipped
 * languages: a deactivated shipped language, or the tenant's own language
 * copied from another one. `revision` changes with every change to the
 * language's strings and is part of the browser bundle version.
 *
 * string_overrides is layered (data model 1.1.4): scope "tenant" rows belong
 * to one tenant; scope "instance" rows (tenant_id NULL) follow with the
 * instance administration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_languages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('locale', 16);
            // Own name of a copied language; shipped languages use the CLDR name.
            $table->string('name', 60)->nullable();
            $table->string('direction', 3)->default('ltr');
            $table->string('based_on', 16)->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('revision')->default(0);
            $table->foreignUlid('changed_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'locale']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'changed_by'])->references(['tenant_id', 'id'])->on('users');
        });

        Schema::create('string_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('scope', 8)->default('tenant');
            $table->foreignUlid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('identifier', 160);
            $table->text('value');
            // Hash of the en source text when the value was written; a different
            // current hash means "source changed, please review".
            $table->string('source_hash', 32)->nullable();
            $table->boolean('needs_review')->default(false);
            $table->foreignUlid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'tenant_id', 'locale', 'identifier']);
            $table->index(['tenant_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('string_overrides');
        Schema::dropIfExists('tenant_languages');
    }
};

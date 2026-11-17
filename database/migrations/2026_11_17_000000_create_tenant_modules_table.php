<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-tenant module state (ADR-0002, S-0901). Disabling a module only flips
 * this row; the module's own data is never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('module_key', 41);
            $table->boolean('enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignUlid('changed_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_key']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'changed_by'])->references(['tenant_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
    }
};

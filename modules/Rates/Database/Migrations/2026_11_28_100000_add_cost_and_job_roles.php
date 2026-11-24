<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0950: cost rates beside sales rates, and job roles as a level of the hierarchy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_rules', fn (Blueprint $table) => $table->string('kind', 16)->default('sales'));
        Schema::table('time_entry_valuations', function (Blueprint $table): void {
            $table->string('kind', 16)->default('sales');
            // First the new index: the foreign key always needs one.
            $table->unique(['tenant_id', 'time_entry_id', 'kind'], 'time_entry_valuations_kind_unique');
        });
        Schema::table('time_entry_valuations', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'time_entry_id']));

        Schema::create('job_roles', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('name', 120);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });
        Schema::create('user_job_roles', function (Blueprint $table): void {
            $this->owned($table);
            $table->foreignUlid('user_id');
            $table->foreignUlid('job_role_id');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'valid_from']);
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'job_role_id'])->references(['tenant_id', 'id'])->on('job_roles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_job_roles');
        Schema::dropIfExists('job_roles');
        Schema::table('time_entry_valuations', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'time_entry_id']);
            $table->dropUnique('time_entry_valuations_kind_unique');
            $table->dropColumn('kind');
        });
        Schema::table('rate_rules', fn (Blueprint $table) => $table->dropColumn('kind'));
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};

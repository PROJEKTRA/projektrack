<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0944, S-0947, S-0948: time entries in whole minutes on calendar dates, and
 * locked months. Approved entries are never changed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $this->owned($table);
            $table->foreignUlid('user_id');
            $table->foreignUlid('project_id');
            $table->foreignUlid('work_package_id');
            $table->date('work_date');
            // Negative only in correction entries.
            $table->integer('duration_minutes');
            $table->integer('billable_minutes');
            $table->text('description')->nullable();
            $table->string('status', 32);
            $table->dateTime('submitted_at')->nullable();
            $table->foreignUlid('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->foreignUlid('corrects_id')->nullable();
            $table->string('correction_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'work_date']);
            $table->index(['tenant_id', 'project_id', 'work_date']);
            $table->index(['tenant_id', 'status']);
            // Booked time keeps its people and packages: they cannot be deleted under it.
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'approved_by'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'project_id'])->references(['tenant_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['tenant_id', 'work_package_id'])->references(['tenant_id', 'id'])->on('work_packages')->restrictOnDelete();
            $table->foreign(['tenant_id', 'corrects_id'])->references(['tenant_id', 'id'])->on('time_entries')->restrictOnDelete();
        });

        Schema::create('time_periods', function (Blueprint $table): void {
            $this->owned($table);
            $table->char('month', 7);
            $table->boolean('locked');
            $table->timestamps();

            $table->unique(['tenant_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_periods');
        Schema::dropIfExists('time_entries');
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};

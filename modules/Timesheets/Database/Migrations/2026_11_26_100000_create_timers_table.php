<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0946: one running timer per user, on the server, so it survives reloads
 * and works on every device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timers', function (Blueprint $table): void {
            $this->owned($table);
            $table->foreignUlid('user_id');
            $table->foreignUlid('work_package_id');
            $table->dateTime('started_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'work_package_id'])->references(['tenant_id', 'id'])->on('work_packages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timers');
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};

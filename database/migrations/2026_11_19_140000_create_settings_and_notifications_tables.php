<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0912: settings declared by the core and modules (settings.schema.json),
 * in-app notifications and each user's e-mail choice per notification type.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Layered values; 0.9 uses scope_type "tenant" with an empty scope_id.
        // scope_id is not nullable, so the unique key works on every database.
        Schema::create('settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 20);
            $table->string('scope_id', 26)->default('');
            $table->string('key', 120);
            $table->json('value');
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'scope_type', 'scope_id', 'key']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 120);
            $table->string('subject_type', 60)->nullable();
            $table->string('subject_id', 26)->nullable();
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'read_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 120);
            $table->string('channel', 20);
            // "immediate" or "off" in 0.9; digests follow.
            $table->string('frequency', 20);
            $table->timestamps();

            $table->unique(['user_id', 'type', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('settings');
    }
};

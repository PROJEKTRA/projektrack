<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0987: shared links to one object (security baseline section 7). Only the
 * SHA-256 of the token is stored; the access log names the link, never the
 * token, and keeps a shortened address and the browser family only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_type', 60);
            $table->string('target_id', 64);
            $table->json('actions');
            $table->char('token', 64)->unique();
            $table->string('password')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUlid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'target_type', 'target_id']);
        });

        Schema::create('shared_link_accesses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('shared_link_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('outcome', 20);
            $table->string('ip_prefix', 45)->nullable();
            $table->string('browser', 20);
            $table->timestamp('occurred_at');

            $table->index(['shared_link_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_link_accesses');
        Schema::dropIfExists('shared_links');
    }
};

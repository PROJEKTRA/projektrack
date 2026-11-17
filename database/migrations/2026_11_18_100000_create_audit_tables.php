<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tamper-evident audit log (S-0907, data model 1.8, security baseline 8).
 *
 * One hash chain per tenant. audit_log is append-only; the application has no
 * update or delete path for it. audit_chain_heads serializes writers and keeps
 * the result of the last verification for the integrity warning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_chain_heads', function (Blueprint $table) {
            $table->foreignUlid('tenant_id')->primary()->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('last_seq')->default(0);
            $table->char('last_hash', 64);
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_seq')->nullable();
            // First entry that failed verification; null while the chain is intact.
            $table->unsignedBigInteger('broken_at_seq')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('seq');
            $table->dateTime('occurred_at', 6);
            $table->string('actor_type', 16);
            $table->ulid('actor_id')->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            // Canonical JSON, stored as text so every engine returns it byte for byte.
            $table->text('context');
            $table->string('ip_address', 45)->nullable();
            $table->ulid('correlation_id')->nullable();
            $table->string('key_id', 16);
            $table->char('prev_hash', 64);
            $table->char('hash', 64);

            $table->unique(['tenant_id', 'seq']);
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
            $table->index(['tenant_id', 'actor_id', 'occurred_at']);
            $table->index(['tenant_id', 'action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('audit_chain_heads');
    }
};

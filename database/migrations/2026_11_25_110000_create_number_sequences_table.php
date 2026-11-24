<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0962: gapless numbers per tenant, document type and year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->char('period_key', 4);
            $table->unsignedInteger('next_value');
            $table->timestamps();

            $table->unique(['tenant_id', 'document_type', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0909: uploaded files. The content lives on the private disk under a
 * random key without file name ending; the original name is only metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('storage_key', 200)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            // pending, clean, infected, skipped (no scanner configured)
            $table->string('scan_status', 20);
            $table->string('context', 40);
            $table->date('retain_until')->nullable();
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

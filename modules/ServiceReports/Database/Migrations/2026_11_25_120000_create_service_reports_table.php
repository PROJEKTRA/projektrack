<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0953, S-0957: a report keeps its content as it was generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_reports', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('number', 40);
            $table->foreignUlid('project_id');
            $table->date('period_from');
            $table->date('period_to');
            $table->string('locale', 12);
            $table->boolean('with_amounts');
            $table->longText('snapshot');
            $table->char('content_hash', 64);
            $table->foreignUlid('created_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'project_id']);
            $table->foreign(['tenant_id', 'project_id'])->references(['tenant_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reports');
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0941: each threshold of a contract budget is told once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_budget_alerts', function (Blueprint $table): void {
            $this->owned($table);
            $table->foreignUlid('contract_id');
            $table->unsignedSmallInteger('threshold_pct');
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_id', 'threshold_pct'], 'contract_budget_alerts_once');
            $table->foreign(['tenant_id', 'contract_id'])->references(['tenant_id', 'id'])->on('contracts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_budget_alerts');
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};

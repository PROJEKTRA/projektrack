<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0951: stored exchange rates, and the rate each valuation used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $this->owned($table);
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->date('rate_date');
            $table->foreignUlid('created_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'from_currency', 'to_currency', 'rate_date'], 'exchange_rates_day');
            $table->foreign(['tenant_id', 'created_by'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
        Schema::table('time_entry_valuations', function (Blueprint $table): void {
            $table->decimal('rule_rate', 19, 4)->nullable();
            $table->char('rule_currency', 3)->nullable();
            $table->string('fx_rate', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('time_entry_valuations', fn (Blueprint $table) => $table->dropColumn(['rule_rate', 'rule_currency', 'fx_rate']));
        Schema::dropIfExists('exchange_rates');
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};

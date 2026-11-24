<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0950: hourly sales rates with validity periods, and the value frozen on
 * approved time. Never changed later, so a new rate is never retroactive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_rules', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('scope', 32);
            $table->char('scope_id', 26)->nullable();
            $table->decimal('rate', 19, 4);
            $table->char('currency', 3);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'scope', 'scope_id', 'valid_from']);
        });

        Schema::create('time_entry_valuations', function (Blueprint $table): void {
            $this->owned($table);
            $table->foreignUlid('time_entry_id');
            $table->foreignUlid('rate_rule_id')->nullable();
            $table->string('source', 32);
            $table->decimal('rate', 19, 4);
            $table->char('currency', 3);
            $table->integer('minutes_valued');
            $table->bigInteger('amount_minor');
            $table->dateTime('valued_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'time_entry_id']);
            $table->foreign(['tenant_id', 'time_entry_id'])->references(['tenant_id', 'id'])->on('time_entries')->restrictOnDelete();
            $table->foreign(['tenant_id', 'rate_rule_id'])->references(['tenant_id', 'id'])->on('rate_rules')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entry_valuations');
        Schema::dropIfExists('rate_rules');
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};

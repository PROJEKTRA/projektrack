<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0959: a tax case chosen by a person, with justification. S-0963: partial
 * credit notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('override_case', 40)->nullable();
            $table->string('override_reason', 500)->nullable();
            $table->foreignUlid('override_by')->nullable();
            $table->foreignUlid('credits_id')->nullable();
            $table->bigInteger('credited_minor')->default(0);
            $table->foreign(['tenant_id', 'override_by'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'credits_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'override_by']);
            $table->dropForeign(['tenant_id', 'credits_id']);
            $table->dropColumn(['override_case', 'override_reason', 'override_by', 'credits_id', 'credited_minor']);
        });
    }
};

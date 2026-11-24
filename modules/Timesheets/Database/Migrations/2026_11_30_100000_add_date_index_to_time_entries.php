<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Month close and margins read all time of a period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', fn (Blueprint $table) => $table->index(['tenant_id', 'work_date'], 'time_entries_tenant_date'));
    }

    public function down(): void
    {
        Schema::table('time_entries', fn (Blueprint $table) => $table->dropIndex('time_entries_tenant_date'));
    }
};

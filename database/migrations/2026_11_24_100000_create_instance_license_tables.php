<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0974: identity and license of this installation. Instance-scoped, not
 * tenant data. One row per license keeps the highest serial ever installed;
 * one of them is active. The token is signed and no secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instance_state', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('instance_id', 40);
            $table->dateTime('latest_time')->nullable();
            $table->timestamps();
        });

        Schema::create('instance_licenses', function (Blueprint $table): void {
            $table->id();
            $table->string('license_id')->unique();
            $table->unsignedBigInteger('serial');
            $table->text('token');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('installed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instance_licenses');
        Schema::dropIfExists('instance_state');
    }
};

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_plans', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('key', 60);
            $table->string('name', 120);
            $table->string('edition', 32);
            $table->unsignedSmallInteger('term_months');
            $table->unsignedSmallInteger('price_lock_months');
            // Per user and term, and once for the onboarding.
            $table->decimal('full_price', 19, 4);
            $table->decimal('light_price', 19, 4);
            $table->decimal('onboarding_price', 19, 4);
            $table->char('currency', 3);
            $table->boolean('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
        Schema::create('license_orders', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('number', 40);
            $table->foreignUlid('license_plan_id');
            $table->foreignUlid('company_id');
            $table->foreignUlid('invoice_id')->nullable();
            // What was agreed, whatever the plan says later.
            $table->string('customer_name', 200);
            $table->decimal('full_price', 19, 4);
            $table->decimal('light_price', 19, 4);
            $table->decimal('onboarding_price', 19, 4);
            $table->char('currency', 3);
            $table->bigInteger('net_minor');
            $table->date('price_locked_until');
            $table->string('license_id', 40);
            $table->unsignedInteger('serial');
            $table->string('status', 32);
            $table->unsignedInteger('full_seats');
            $table->unsignedInteger('light_seats');
            $table->string('instance_id', 40);
            $table->string('domain', 253);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->dateTime('pending_until')->nullable();
            $table->dateTime('reminded_at')->nullable();
            $table->text('token')->nullable();
            $table->unsignedInteger('issued_serial')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'license_id']);
            $table->foreign(['tenant_id', 'license_plan_id'])->references(['tenant_id', 'id'])->on('license_plans')->restrictOnDelete();
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
        });
        Schema::create('license_order_events', function (Blueprint $table): void {
            $this->owned($table);
            $table->foreignUlid('license_order_id');
            $table->string('event', 32);
            $table->foreignUlid('actor_id')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'license_order_id'])->references(['tenant_id', 'id'])->on('license_orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['license_order_events', 'license_orders', 'license_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }
};

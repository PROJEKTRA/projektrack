<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0961 to S-0966: invoices in minor units. A number exists only once
 * finalized; finalized documents are never changed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('kind', 32);
            $table->string('status', 32);
            $table->string('number', 40)->nullable();
            $table->foreignUlid('company_id');
            $table->foreignUlid('project_id')->nullable();
            $table->foreignUlid('cancels_id')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('service_from');
            $table->date('service_to');
            $table->date('due_date')->nullable();
            $table->char('currency', 3);
            $table->string('locale', 12);
            $table->bigInteger('net_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->longText('snapshot')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->foreignUlid('finalized_by')->nullable();
            $table->string('fiscal_reference', 120)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            // At most one cancellation per invoice.
            $table->unique(['tenant_id', 'cancels_id']);
            $table->index(['tenant_id', 'status', 'due_date']);
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->restrictOnDelete();
            $table->foreign(['tenant_id', 'project_id'])->references(['tenant_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['tenant_id', 'cancels_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'finalized_by'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofInvoice($table);
            $table->unsignedSmallInteger('position');
            $table->string('description', 500);
            // Time lines hold whole minutes, all others a quantity.
            $table->integer('minutes')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 32);
            $table->decimal('unit_price', 19, 4);
            $table->string('rate_class', 32);
            $table->bigInteger('net_minor');
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_id', 'position']);
        });

        Schema::create('invoice_times', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofInvoice($table);
            $table->foreignUlid('time_entry_id');
            // Holds the entry while the invoice stands; emptied when it is cancelled.
            $table->char('active_key', 26)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'active_key']);
            $table->foreign(['tenant_id', 'time_entry_id'])->references(['tenant_id', 'id'])->on('time_entries')->restrictOnDelete();
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofInvoice($table);
            $table->date('paid_on');
            $table->bigInteger('amount_minor');
            $table->string('note', 191)->nullable();
            $table->foreignUlid('recorded_by');
            $table->timestamps();

            $table->foreign(['tenant_id', 'recorded_by'])->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['invoice_payments', 'invoice_times', 'invoice_lines', 'invoices'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }

    private function ofInvoice(Blueprint $table): void
    {
        $table->foreignUlid('invoice_id');
        $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
    }
};

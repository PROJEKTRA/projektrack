<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S-0940 to S-0942: projects, members, contracts and work packages (data model
 * section 6). Foreign keys carry the tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $this->owned($table);
            $table->string('number', 20);
            $table->string('name', 191);
            $table->foreignUlid('company_id');
            $table->string('status', 32);
            $table->string('billing_model', 32);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('budget_minutes')->nullable();
            $table->bigInteger('budget_minor')->nullable();
            $table->char('currency', 3);
            $table->unsignedSmallInteger('amber_percent')->default(90);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'company_id', 'status']);
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->restrictOnDelete();
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofProject($table);
            $table->foreignUlid('user_id');
            $table->foreignUlid('project_role_id');
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'project_id', 'user_id', 'project_role_id'], 'project_members_unique');
            $table->index(['tenant_id', 'user_id']);
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'project_role_id'])->references(['tenant_id', 'id'])->on('project_roles')->restrictOnDelete();
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofProject($table);
            $table->string('number', 60);
            $table->string('contract_type', 32);
            $table->string('status', 32);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('budget_minutes')->nullable();
            $table->unsignedSmallInteger('rounding_minutes')->default(1);
            $table->unsignedSmallInteger('minimum_minutes')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('work_packages', function (Blueprint $table): void {
            $this->owned($table);
            $this->ofProject($table);
            $table->foreignUlid('parent_id')->nullable();
            $table->foreignUlid('contract_id')->nullable();
            $table->string('wbs_code', 60);
            $table->string('path', 120);
            $table->unsignedSmallInteger('sort_order');
            $table->string('name', 191);
            $table->string('status', 32);
            $table->integer('planned_minutes')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('percent_complete')->default(0);
            $table->boolean('billable')->default(true);
            $table->string('service_category', 32)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'project_id', 'path']);
            $table->foreign(['tenant_id', 'parent_id'])->references(['tenant_id', 'id'])->on('work_packages')->restrictOnDelete();
            $table->foreign(['tenant_id', 'contract_id'])->references(['tenant_id', 'id'])->on('contracts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['work_packages', 'contracts', 'project_members', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function owned(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->unique(['tenant_id', 'id']);
    }

    private function ofProject(Blueprint $table): void
    {
        $table->foreignUlid('project_id');
        $table->foreign(['tenant_id', 'project_id'])->references(['tenant_id', 'id'])->on('projects')->cascadeOnDelete();
    }
};

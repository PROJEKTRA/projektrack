<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Roles, project roles and field-level permissions (S-0906, data model section 2).
 *
 * The permission catalog is instance-scoped; everything else belongs to one
 * tenant and links through tenant-safe composite foreign keys, so a role of one
 * tenant can never be granted to a user of another.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->string('key', 120)->primary();
            $table->string('module_key', 41);
            $table->string('risk_level', 16)->default('normal');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 64);
            // String identifier for system roles; custom roles are named in role_translations.
            $table->string('name_string', 120)->nullable();
            $table->string('template_key', 64)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_portal')->default(false);
            $table->boolean('requires_two_factor')->default(false);
            // Derived from the granted permissions: full, light or portal.
            $table->string('licensed_user_type', 16)->default('light');
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('role_translations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('role_id');
            $table->string('locale', 16);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'locale']);
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('role_id');
            $table->string('permission_key', 120);
            $table->timestamp('created_at')->nullable();

            $table->primary(['role_id', 'permission_key']);
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
            $table->foreign('permission_key')->references('key')->on('permissions')->restrictOnDelete();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('user_id');
            $table->foreignUlid('role_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'role_id']);
            $table->index(['tenant_id', 'role_id']);
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
        });

        Schema::create('field_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('role_id');
            $table->string('entity_type', 64);
            $table->string('field', 64);
            $table->string('access', 8);
            $table->timestamps();

            $table->unique(['role_id', 'entity_type', 'field']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
        });

        Schema::create('project_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name_string', 120)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('project_role_translations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('project_role_id');
            $table->string('locale', 16);
            $table->string('name', 120);
            $table->timestamps();

            $table->unique(['project_role_id', 'locale']);
            $table->foreign(['tenant_id', 'project_role_id'])->references(['tenant_id', 'id'])->on('project_roles')->cascadeOnDelete();
        });

        Schema::create('project_role_permissions', function (Blueprint $table) {
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('project_role_id');
            $table->string('permission_key', 120);
            $table->timestamp('created_at')->nullable();

            $table->primary(['project_role_id', 'permission_key']);
            $table->foreign(['tenant_id', 'project_role_id'])->references(['tenant_id', 'id'])->on('project_roles')->cascadeOnDelete();
            $table->foreign('permission_key')->references('key')->on('permissions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_role_permissions');
        Schema::dropIfExists('project_role_translations');
        Schema::dropIfExists('project_roles');
        Schema::dropIfExists('field_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('role_translations');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};

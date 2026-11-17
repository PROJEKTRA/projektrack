<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Providers;

use App\Authorization\FieldGate;
use App\Authorization\NoProjectMemberships;
use App\Authorization\PermissionCatalog;
use App\Authorization\Permissions;
use App\Authorization\ProjectMemberships;
use App\Authorization\ProjectScoped;
use App\Authorization\RoleManager;
use App\Authorization\RoleTemplates;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\ModuleDisabled;
use App\Modules\ModuleEnabled;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Roles, project roles and field-level permissions (S-0906).
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionCatalog::class);

        // The projects module rebinds this with its project_members lookup.
        $this->app->bindIf(ProjectMemberships::class, NoProjectMemberships::class, true);

        // Per request or job: caches must never outlive a role change elsewhere.
        $this->app->scoped(Permissions::class);
        $this->app->scoped(FieldGate::class);
        $this->app->scoped(RoleManager::class);
        $this->app->scoped(RoleTemplates::class);
    }

    public function boot(): void
    {
        // Permission keys are Gate abilities: $user->can('timesheets.approve', $timeEntry).
        // Keys nobody registered fall through and are denied (no ability defined).
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if (! $this->app->make(PermissionCatalog::class)->has($ability)) {
                return null;
            }

            $subject = $arguments[0] ?? null;
            $projectId = $subject instanceof ProjectScoped ? $subject->scopedProjectId() : null;

            return $this->app->make(Permissions::class)->allows($user, $ability, $projectId);
        });

        // Every new tenant starts with the role templates.
        Tenant::created(fn (Tenant $tenant) => $this->app->make(RoleTemplates::class)->install($tenant));

        Event::listen(ModuleEnabled::class, function (ModuleEnabled $event): void {
            $this->app->make(Permissions::class)->flush();
            $this->app->make(FieldGate::class)->flush();

            if ($event->firstTime && $event->tenantId === $this->app->make(TenantContext::class)->id()) {
                $this->app->make(RoleManager::class)->applyModuleDefaults($event->module);
            }
        });

        Event::listen(ModuleDisabled::class, function (): void {
            $this->app->make(Permissions::class)->flush();
            $this->app->make(FieldGate::class)->flush();
        });
    }
}

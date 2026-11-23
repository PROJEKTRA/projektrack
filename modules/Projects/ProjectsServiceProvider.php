<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects;

use App\Authorization\ProjectMemberships;
use App\Dashboard\Dashboard;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Modules\Projects\Contracts\BookedTime;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Projects\Domain\Directory;
use Modules\Projects\Domain\Memberships;
use Modules\Projects\Domain\NoBookedTime;
use Modules\Projects\Domain\Project;

/**
 * Projects, contracts and work packages (S-0940, S-0941, S-0942).
 */
class ProjectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProjectMemberships::class, Memberships::class);
        $this->app->bind(ProjectDirectory::class, Directory::class);
        // The time tracking module rebinds this.
        $this->app->bindIf(BookedTime::class, NoBookedTime::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->app->make(Dashboard::class)->add('projects', fn (User $user): array => $user->can('projects.view') ? [['label' => 'projects:dashboard.active', 'value' => (string) Project::query()->where('status', 'active')->count(), 'href' => route('projects.index', absolute: false)]] : []);
    }
}

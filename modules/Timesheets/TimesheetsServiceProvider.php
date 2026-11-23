<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets;

use App\Authorization\Permissions;
use App\Dashboard\Dashboard;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;
use Modules\Projects\Contracts\BookedTime;
use Modules\Timesheets\Contracts\ApprovedTime;
use Modules\Timesheets\Domain\ApprovedEntries;
use Modules\Timesheets\Domain\EntryTotals;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\Timesheets;

/**
 * Time tracking (S-0944, S-0945, S-0947, S-0948).
 */
class TimesheetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BookedTime::class, EntryTotals::class);
        $this->app->bind(ApprovedTime::class, ApprovedEntries::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->app->make(Dashboard::class)->add('timesheets', function (User $user): array {
            $cards = [];
            if ($user->can('timesheets.view')) {
                [$from, $to] = Timesheets::week(now($user->timezone ?? $this->app->make(TenantContext::class)->current()->timezone)->toDateString());
                $minutes = (int) TimeEntry::query()->where('user_id', $user->id)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->sum('duration_minutes');
                $cards[] = ['label' => 'timesheets:dashboard.week', 'value' => sprintf('%s%d:%02d', $minutes < 0 ? '-' : '', intdiv(abs($minutes), 60), abs($minutes) % 60), 'href' => route('timesheets.index', absolute: false)];
            }
            if (in_array('timesheets.approve', $this->app->make(Permissions::class)->anywhere($user), true)) {
                $cards[] = ['label' => 'timesheets:dashboard.approvals', 'value' => (string) $this->app->make(Timesheets::class)->pendingFor($user)->count(), 'href' => route('timesheets.approvals.index', absolute: false)];
            }

            return $cards;
        });
    }
}

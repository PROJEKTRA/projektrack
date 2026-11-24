<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices;

use App\Dashboard\Dashboard;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Console\DemoData;
use Modules\Invoices\Domain\Invoice;

/**
 * Invoices: draft, finalize, cancel, payments (S-0961 to S-0966).
 */
class InvoicesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        $this->app->make(Dashboard::class)->step('invoices', 'invoices:dashboard.step', fn (): string => route('invoices.index', absolute: false), fn (): bool => Invoice::query()->where('status', 'finalized')->exists());
        $this->app->make(Dashboard::class)->add('invoices', function (User $user): array {
            if (! $user->can('invoices.view')) {
                return [];
            }
            $open = fn () => Invoice::query()->where('kind', 'invoice')->where('status', 'finalized')->whereRaw('paid_minor < gross_minor + credited_minor');
            $overdue = $open()->whereDate('due_date', '<', now($this->app->make(TenantContext::class)->current()->timezone)->toDateString())->count();

            return [
                ['label' => 'invoices:dashboard.open', 'value' => (string) $open()->count(), 'href' => route('invoices.index', ['show' => 'open'], absolute: false)],
                ['label' => 'invoices:dashboard.overdue', 'value' => (string) $overdue, 'href' => route('invoices.index', ['show' => 'open'], absolute: false), 'alert' => $overdue > 0],
            ];
        });
        $this->commands([DemoData::class]);
        $this->loadViewsFrom(__DIR__.'/resources/views', 'invoices');
    }
}

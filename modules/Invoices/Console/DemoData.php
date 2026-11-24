<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Console;

use App\Authorization\Permissions;
use App\Authorization\RoleManager;
use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Crm\Domain\Companies;
use Modules\Crm\Domain\Company;
use Modules\Invoices\Domain\Invoices;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\ProjectMember;
use Modules\Projects\Domain\WorkBreakdown;
use Modules\Rates\Domain\Rates;
use Modules\ServiceReports\Domain\Reports;
use Modules\Timesheets\Domain\Timesheets;

/**
 * Synthetic sample data for trying the product (S-0937): never over real data,
 * never on a production installation, and no invoice is issued.
 */
class DemoData extends Command
{
    protected $signature = 'projektrack:demo {tenant : Slug of the tenant}';

    protected $description = 'Fill an empty test installation with synthetic sample data';

    public function handle(TenantContext $context): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('tenant'))->first();
        if ($tenant === null) {
            $this->error('Unknown tenant.');

            return self::FAILURE;
        }
        if ($this->laravel->environment('production')) {
            $this->error('Not on a production installation (APP_ENV).');

            return self::FAILURE;
        }

        return $context->run($tenant, function (): int {
            foreach (['service_reports', 'invoices'] as $module) {
                app(TenantModules::class)->enable($module);
            }
            if (Company::query()->exists()) {
                $this->error('This tenant has customers already. Sample data goes into an empty one only.');

                return self::FAILURE;
            }
            DB::transaction(fn () => $this->fill());
            $this->info('Sample data added: 3 customers, 5 projects, 8 people, 3 months of approved time, reports and draft invoices.');

            return self::SUCCESS;
        });
    }

    private function fill(): void
    {
        $tenant = app(TenantContext::class)->current();
        $person = function (string $name, string $role): User {
            // No usable password: nobody signs in with sample people.
            $user = User::query()->forceCreate(['name' => $name, 'email' => Str::slug($name).'@example.test', 'password' => Hash::make(Str::random(64))]);
            app(RoleManager::class)->assign($user, Role::query()->where('key', $role)->firstOrFail());

            return $user;
        };
        $controller = $person('Demo Kontroler', 'controller');
        $lead = $person('Demo Voditelj', 'project_lead');
        $team = array_map(fn (int $i): User => $person("Demo Saradnik {$i}", 'employee'), range(1, 6));
        app(Permissions::class)->flush();

        $companies = array_map(fn (int $i): Company => app(Companies::class)->create([
            'company' => ['name' => "Primjer Klijent {$i} d.o.o.", 'kind' => 'customer', 'document_locale' => 'bs', 'currency' => $tenant->base_currency, 'payment_days' => 14, 'einvoice_format' => 'pdf'],
            'tax' => ['tax_status' => 'domestic', 'country_code' => $tenant->country_code, 'tax_number' => '000000000000'.$i],
            'address' => ['line1' => "Izmišljena ulica {$i}", 'postal_code' => '00000', 'city' => 'Primjergrad', 'country_code' => $tenant->country_code],
            'contacts' => [['last_name' => "Kontakt {$i}", 'email' => "klijent{$i}@example.test", 'invoice_recipient' => true]],
        ], $controller), [1, 2, 3]);

        $start = CarbonImmutable::parse(now($tenant->timezone)->startOfMonth()->subMonths(3)->toDateString(), 'UTC');
        $end = CarbonImmutable::parse(now($tenant->timezone)->startOfMonth()->subDay()->toDateString(), 'UTC');
        app(Rates::class)->add('tenant', null, '100', $tenant->base_currency, $start->subYear()->toDateString(), $controller);
        app(Rates::class)->add('user', $team[0]->id, '80', $tenant->base_currency, $start->subYear()->toDateString(), $controller);
        app(Rates::class)->add('user', $team[1]->id, '130', $tenant->base_currency, $start->subYear()->toDateString(), $controller);

        $role = fn (string $key): string => ProjectRole::query()->where('key', $key)->firstOrFail()->id;
        foreach (['Uvođenje sistema', 'Održavanje', 'Savjetovanje', 'Migracija', 'Podrška'] as $n => $name) {
            $project = Project::query()->create(['number' => sprintf('P-%05d', $n + 1), 'name' => $name, 'company_id' => $companies[$n % 3]->id, 'status' => 'active', 'billing_model' => 'time_and_material', 'currency' => $tenant->base_currency, 'budget_minutes' => 30000]);
            ProjectMember::query()->create(['project_id' => $project->id, 'user_id' => $lead->id, 'project_role_id' => $role('project_lead')]);
            $members = [$team[$n], $team[($n + 1) % 6]];
            $packages = array_map(fn (string $p) => app(WorkBreakdown::class)->add($project, ['name' => $p, 'billable' => true], null), ['Upravljanje projektom', 'Realizacija']);
            foreach ($members as $member) {
                ProjectMember::query()->create(['project_id' => $project->id, 'user_id' => $member->id, 'project_role_id' => $role('project_member')]);
            }
            $ids = [];
            for ($day = $start; $day <= $end; $day = $day->addDay()) {
                // Two days a week per project, so nobody has more than a working day.
                if (in_array($day->dayOfWeekIso, [$n % 5 + 1, ($n + 2) % 5 + 1], true)) {
                    foreach ($members as $m => $member) {
                        $entry = app(Timesheets::class)->book($member, $packages[$m]->id, $day->toDateString(), 120 + 30 * (($n + $day->day) % 4), 'Primjer opisa rada');
                        $ids[] = (string) $entry?->id;
                        app(Timesheets::class)->submitWeek($member, $day->toDateString());
                    }
                }
            }
            foreach (array_chunk($ids, 200) as $chunk) {
                app(Timesheets::class)->approve($controller, $chunk);
            }
            app(Reports::class)->generate($project->id, $end->startOfMonth()->toDateString(), $end->toDateString(), false, $controller);
            if ($n < 2) {
                app(Invoices::class)->draftFromTime($project->id, $end->startOfMonth()->toDateString(), $end->toDateString(), $controller);
            }
        }
    }
}

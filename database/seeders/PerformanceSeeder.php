<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Database\Seeders;

use App\Models\ProjectRole;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Synthetic load of the performance test (tests/performance): 100,000 time
 * entries of 49 people in one tenant (performance budget, assumption A-12).
 * Never run in production.
 */
class PerformanceSeeder extends Seeder
{
    private const int PEOPLE = 49;

    private const int COMPANIES = 200;

    private const int PROJECTS = 300;

    private const int ENTRIES = 100_000;

    private const int INVOICES = 2_000;

    public function run(): void
    {
        $this->call(E2eSeeder::class);

        $tenant = Tenant::query()->where('slug', 'e2e-company')->firstOrFail()->id;
        $now = now()->toDateTimeString();
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $owned = fn (): array => ['id' => strtolower((string) Str::ulid()), 'tenant_id' => $tenant, 'created_at' => $now, 'updated_at' => $now];
        // The person who signs in books time like everybody else.
        $me = DB::table('users')->where('email', E2eSeeder::USERS['bs']['email'])->value('id');

        $hash = DB::table('users')->where('id', $me)->value('password');
        $people = [$me];
        $rows = [];
        for ($i = 1; $i < self::PEOPLE; $i++) {
            $rows[] = $user = [...$owned(), 'name' => "Person {$i}", 'email' => "person{$i}@example.test", 'email_verified_at' => $now, 'password' => $hash, 'locale' => 'bs'];
            $people[] = $user['id'];
        }
        $this->insert('users', $rows);

        $companies = [];
        $rows = [];
        for ($i = 1; $i <= self::COMPANIES; $i++) {
            $rows[] = $company = [...$owned(), 'number' => "P-{$i}", 'name' => "Customer {$i}", 'kind' => 'customer', 'status' => 'active', 'document_locale' => 'en', 'currency' => 'BAM', 'payment_days' => 14, 'einvoice_format' => 'pdf'];
            $companies[] = $company['id'];
        }
        $this->insert('companies', $rows);

        $projects = [];
        $packages = [];
        $rows = [];
        $wbs = [];
        for ($i = 1; $i <= self::PROJECTS; $i++) {
            $rows[] = $project = [...$owned(), 'number' => "X-{$i}", 'name' => "Project {$i}", 'company_id' => $companies[$i % self::COMPANIES], 'status' => $i % 4 === 0 ? 'closed' : 'active', 'billing_model' => 'time_and_material', 'budget_minutes' => 60_000, 'currency' => 'BAM', 'amber_percent' => 90];
            $projects[] = $project['id'];
            for ($p = 1; $p <= 5; $p++) {
                $wbs[] = $package = [...$owned(), 'project_id' => $project['id'], 'wbs_code' => (string) $p, 'path' => sprintf('%04d', $p), 'sort_order' => $p, 'name' => "Package {$p}", 'status' => 'open', 'percent_complete' => 0, 'billable' => true];
                $packages[$project['id']][] = $package['id'];
            }
        }
        $this->insert('projects', $rows);
        $this->insert('work_packages', $wbs);

        // Everybody works in 12 projects.
        $role = (string) ProjectRole::query()->withoutGlobalScopes()->where('tenant_id', $tenant)->where('key', 'project_lead')->firstOrFail()->id;
        $works = [];
        $rows = [];
        foreach ($people as $n => $person) {
            for ($k = 0; $k < 12; $k++) {
                $works[$n][] = $project = $projects[($n * 6 + $k) % self::PROJECTS];
                $rows[] = [...$owned(), 'project_id' => $project, 'user_id' => $person, 'project_role_id' => $role];
            }
        }
        $this->insert('project_members', $rows);

        $rows = [];
        $values = [];
        for ($i = 0; $i < self::ENTRIES; $i++) {
            $n = $i % self::PEOPLE;
            $project = $works[$n][$i % 12];
            $day = $today->subDays(intdiv($i, self::PEOPLE * 3));
            $status = $i < 3_000 ? ($i % 2 === 0 ? 'submitted' : 'draft') : 'approved';
            $rows[] = $entry = [...$owned(), 'user_id' => $people[$n], 'project_id' => $project, 'work_package_id' => $packages[$project][$i % 5], 'work_date' => $day->toDateString(), 'duration_minutes' => 90, 'billable_minutes' => 90, 'description' => "Work {$i}", 'status' => $status, 'submitted_at' => $status === 'draft' ? null : $now, 'approved_by' => $status === 'approved' ? $me : null, 'approved_at' => $status === 'approved' ? $now : null];
            if ($status === 'approved') {
                foreach (['sales' => 8_000, 'cost' => 4_000] as $kind => $hour) {
                    $values[] = [...$owned(), 'time_entry_id' => $entry['id'], 'kind' => $kind, 'source' => 'tenant', 'rate' => sprintf('%d.0000', intdiv($hour, 100)), 'currency' => 'BAM', 'minutes_valued' => 90, 'amount_minor' => intdiv($hour * 90, 60), 'valued_at' => $now];
                }
            }
            if (count($rows) === 1_000) {
                $this->insert('time_entries', $rows);
                $this->insert('time_entry_valuations', $values);
                [$rows, $values] = [[], []];
            }
        }

        $rows = [];
        $snapshot = (string) json_encode(['lines' => [], 'groups' => [], 'notices' => []]);
        for ($i = 1; $i <= self::INVOICES; $i++) {
            $day = $today->subDays($i % 700)->toDateString();
            $rows[] = [...$owned(), 'kind' => 'invoice', 'status' => 'finalized', 'number' => sprintf('PERF-%05d', $i), 'company_id' => $companies[$i % self::COMPANIES], 'issue_date' => $day, 'service_from' => $day, 'service_to' => $day, 'due_date' => $day, 'currency' => 'BAM', 'locale' => 'en', 'net_minor' => 10_000, 'tax_minor' => 1_700, 'gross_minor' => 11_700, 'paid_minor' => $i % 3 === 0 ? 0 : 11_700, 'snapshot' => $snapshot, 'content_hash' => hash('sha256', $snapshot), 'finalized_at' => $now, 'finalized_by' => $me];
        }
        $this->insert('invoices', $rows);
    }

    /** @param list<array<string, mixed>> $rows */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}

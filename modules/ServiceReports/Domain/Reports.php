<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\ServiceReports\Domain;

use App\Audit\AuditLog;
use App\Audit\CanonicalJson;
use App\Documents\Numbers;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Rates\Contracts\TimeValues;
use Modules\Timesheets\Contracts\ApprovedTime;

final class Reports
{
    public function __construct(
        private readonly ApprovedTime $time,
        private readonly TimeValues $values,
        private readonly ProjectDirectory $projects,
        private readonly CompanyDirectory $companies,
        private readonly TenantContext $tenant,
        private readonly Numbers $numbers,
        private readonly AuditLog $audit,
    ) {}

    public function generate(string $projectId, string $from, string $to, bool $withAmounts, User $actor): ServiceReport
    {
        $project = $this->projects->projects([$projectId])[$projectId] ?? abort(404);
        $rows = $this->time->between($projectId, $from, $to);
        if ($rows === []) {
            throw ValidationException::withMessages(['period_from' => __('service_reports:errors.empty')]);
        }
        $company = $this->companies->billing($project['company_id'], $to);
        $packages = $this->projects->workPackages(array_values(array_unique(array_column($rows, 'work_package_id'))));
        $values = $withAmounts ? $this->values->of(array_column($rows, 'id')) : [];

        $lines = [];
        $totals = ['package' => [], 'user' => []];
        $minutes = 0;
        $amounts = [];
        $unvalued = 0;
        foreach ($rows as $row) {
            $package = trim(($packages[$row['work_package_id']]['code'] ?? '').' '.($packages[$row['work_package_id']]['name'] ?? ''));
            $value = $values[$row['id']] ?? null;
            $line = ['date' => $row['date'], 'user' => $row['user'], 'package' => $package, 'minutes' => $row['billable_minutes'], 'description' => $row['description'], 'correction' => $row['correction']];
            foreach (['package' => $package, 'user' => $row['user']] as $by => $key) {
                $totals[$by][$key] = ($totals[$by][$key] ?? 0) + $row['billable_minutes'];
            }
            $minutes += $row['billable_minutes'];
            if ($withAmounts) {
                $line += ['rate' => $value['rate'] ?? null, 'currency' => $value['currency'] ?? null, 'amount_minor' => $value['amount_minor'] ?? null];
                if ($value === null) {
                    $unvalued += $row['billable_minutes'] === 0 ? 0 : 1;
                } else {
                    $amounts[$value['currency']] = ($amounts[$value['currency']] ?? 0) + $value['amount_minor'];
                }
            }
            $lines[] = $line;
        }

        return DB::transaction(function () use ($project, $company, $from, $to, $withAmounts, $actor, $lines, $totals, $minutes, $amounts, $unvalued): ServiceReport {
            $number = $this->numbers->next('service_report', 'SR');
            $snapshot = CanonicalJson::encode([
                'number' => $number, 'seller' => $this->tenant->current()->name, 'company' => $company['name'] ?? '', 'project' => "{$project['number']} {$project['name']}",
                'from' => $from, 'to' => $to, 'lines' => $lines, 'totals' => $totals, 'minutes' => $minutes, 'amounts' => $amounts, 'unvalued' => $unvalued,
            ]);
            $report = ServiceReport::query()->create([
                'number' => $number, 'project_id' => $project['id'], 'period_from' => $from, 'period_to' => $to, 'locale' => $company['document_locale'] ?? $this->tenant->current()->default_locale,
                'with_amounts' => $withAmounts, 'snapshot' => $snapshot, 'content_hash' => hash('sha256', $snapshot), 'created_by' => $actor->id,
            ]);
            $this->audit->record('service_reports.generated', $report, ['number' => $number], $actor);

            return $report;
        }, 3);
    }
}

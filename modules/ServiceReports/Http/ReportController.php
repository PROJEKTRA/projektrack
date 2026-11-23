<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\ServiceReports\Http;

use App\Authorization\Permissions;
use App\Documents\Pdf;
use App\Domain\Money\Amount;
use App\Http\Controllers\Controller;
use App\I18n\Strings;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\ServiceReports\Domain\Reports;
use Modules\ServiceReports\Domain\ServiceReport;
use Modules\Timesheets\Contracts\ApprovedTime;

class ReportController extends Controller
{
    public function __construct(private readonly Permissions $permissions) {}

    public function index(Request $request, ProjectDirectory $projects, TenantContext $tenant): Response
    {
        $user = $this->currentUser($request);
        abort_unless(in_array('service_reports.view', $this->permissions->anywhere($user), true), 403);

        $visible = fn (string $permission): \Closure => fn (array $p): bool => $this->permissions->allows($user, $permission, is_string($p['id'] ?? null) ? $p['id'] : null);
        $reports = ServiceReport::query()->orderByDesc('created_at')->orderByDesc('number')->limit(500)->get()
            ->filter(fn (ServiceReport $r): bool => $this->allowed($user, $r))->values();
        $names = $projects->projects(array_values($reports->map(fn (ServiceReport $r): string => $r->project_id)->unique()->all()));
        $month = now($tenant->current()->timezone)->startOfMonth();

        return Inertia::render('ServiceReports::Reports', [
            'reports' => $reports->map(fn (ServiceReport $r): array => [
                'id' => $r->id, 'number' => $r->number, 'project' => $names[$r->project_id]['name'] ?? '', 'from' => $r->period_from->toDateString(), 'to' => $r->period_to->toDateString(), 'with_amounts' => $r->with_amounts,
            ])->all(),
            'projects' => array_map(fn (array $p): array => ['value' => $p['id'], 'text' => "{$p['number']} {$p['name']}", 'amounts' => $this->permissions->allows($user, 'service_reports.view_amounts', $p['id'])], array_values(array_filter($projects->active(), $visible('service_reports.generate')))),
            'from' => $month->copy()->subMonth()->toDateString(),
            'to' => $month->copy()->subDay()->toDateString(),
        ]);
    }

    public function store(Request $request, Reports $reports, ApprovedTime $time): RedirectResponse
    {
        $user = $this->currentUser($request);
        $request->validate(['project_id' => ['required', 'ulid'], 'period_from' => ['required', 'date_format:Y-m-d'], 'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from'], 'with_amounts' => ['boolean']]);
        $project = $request->string('project_id')->toString();
        $amounts = $request->boolean('with_amounts');
        abort_unless($this->permissions->allows($user, 'service_reports.generate', $project), 403);
        abort_if($amounts && ! $this->permissions->allows($user, 'service_reports.view_amounts', $project), 403);

        [$from, $to] = [$request->string('period_from')->toString(), $request->string('period_to')->toString()];
        $report = $reports->generate($project, $from, $to, $amounts, $user);
        $open = $time->openCount($project, $from, $to);
        Inertia::flash('toast', ['type' => $open === 0 ? 'success' : 'warning', 'message' => __($open === 0 ? 'service_reports:generated' : 'service_reports:generated_open', ['number' => $report->number, 'count' => $open])]);

        return to_route('service_reports.index');
    }

    public function pdf(Request $request, string $report, Pdf $pdf): HttpResponse
    {
        $model = $this->find($request, $report);

        return response($pdf->render('service_reports::report', ['r' => $model->content(), 'amounts' => $model->with_amounts], $model->locale, $model->number), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$model->number}.pdf\"", 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function csv(Request $request, string $report, Strings $strings): HttpResponse
    {
        $model = $this->find($request, $report);
        $content = $model->content();
        $head = ['date', 'user', 'package', 'minutes', 'description', ...($model->with_amounts ? ['rate', 'currency', 'amount'] : [])];
        $out = [array_map(fn (string $c): string => $strings->get("service_reports:column.{$c}", [], $model->locale), $head)];
        foreach (is_array($content['lines'] ?? null) ? $content['lines'] : [] as $line) {
            if (is_array($line)) {
                $minor = $line['amount_minor'] ?? null;
                $currency = $line['currency'] ?? null;
                $line['amount'] = is_int($minor) && is_string($currency) ? Amount::decimal($minor, $currency) : '';
                $out[] = array_map(fn (string $c): string => self::cell($line[$c] ?? ''), $head);
            }
        }
        $csv = implode("\r\n", array_map(fn (array $row): string => implode(';', array_map(fn (string $v): string => '"'.str_replace('"', '""', $v).'"', $row)), $out))."\r\n";

        return response("\xEF\xBB\xBF".$csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$model->number}.csv\"", 'Cache-Control' => 'private, no-store']);
    }

    /** Text that a spreadsheet would run as a formula stays text; numbers stay numbers. */
    private static function cell(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return preg_match('/^[=+@\t\r]|^-(?![0-9]+(\.[0-9]+)?\z)/', $text) === 1 ? "'".$text : $text;
    }

    private function find(Request $request, string $id): ServiceReport
    {
        $report = ServiceReport::query()->findOrFail($id);
        abort_unless($this->allowed($this->currentUser($request), $report), 403);

        return $report;
    }

    private function allowed(User $user, ServiceReport $report): bool
    {
        return $this->permissions->allows($user, 'service_reports.view', $report->project_id)
            && (! $report->with_amounts || $this->permissions->allows($user, 'service_reports.view_amounts', $report->project_id));
    }
}

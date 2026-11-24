<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\ServiceReports\Http;

use App\Audit\AuditLog;
use App\Authorization\Permissions;
use App\Documents\Pdf;
use App\Domain\Money\Amount;
use App\Http\Controllers\Controller;
use App\I18n\Strings;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\ServiceReports\Domain\Reports;
use Modules\ServiceReports\Domain\ReportSheet;
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
        $reports = ServiceReport::query()->select(['id', 'tenant_id', 'number', 'project_id', 'period_from', 'period_to', 'with_amounts', 'created_at'])->orderByDesc('created_at')->orderByDesc('number')->limit(500)->get()
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
        $request->validate(['period_to' => ['before_or_equal:'.CarbonImmutable::parse($request->string('period_from')->toString(), 'UTC')->addDays(366)->toDateString()]]);
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

    public function pdf(Request $request, string $report, Pdf $pdf, AuditLog $audit): HttpResponse
    {
        $model = $this->find($request, $report);
        $content = $model->content();
        $audit->record('service_reports.downloaded', $model, ['number' => $model->number], $this->currentUser($request));
        $format = $content['format'] ?? null;

        return response($pdf->render('service_reports::report', ['r' => $content, 'amounts' => $model->with_amounts], $model->locale, $model->number, is_array($content['labels'] ?? null) ? $content['labels'] : [], is_string($format) ? $format : null), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$model->number}.pdf\"", 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function xlsx(Request $request, string $report, ReportSheet $sheet, AuditLog $audit): HttpResponse
    {
        $model = $this->find($request, $report);
        $file = $sheet->render($model);
        $audit->record('service_reports.exported', $model, ['number' => $model->number, 'as' => 'xlsx'], $this->currentUser($request));

        return response($file, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => "attachment; filename=\"{$model->number}.xlsx\"", 'Cache-Control' => 'private, no-store']);
    }

    public function csv(Request $request, string $report, Strings $strings, AuditLog $audit): HttpResponse
    {
        $model = $this->find($request, $report);
        $audit->record('service_reports.exported', $model, ['number' => $model->number], $this->currentUser($request));
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
        // Hidden reports look like missing ones.
        abort_unless($this->allowed($this->currentUser($request), $report), 404);

        return $report;
    }

    private function allowed(User $user, ServiceReport $report): bool
    {
        return $this->permissions->allows($user, 'service_reports.view', $report->project_id)
            && (! $report->with_amounts || $this->permissions->allows($user, 'service_reports.view_amounts', $report->project_id));
    }
}

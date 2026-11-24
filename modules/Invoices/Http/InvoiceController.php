<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Http;

use App\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Brick\Math\Exception\MathException;
use Brick\Money\Currency;
use Brick\Money\Exception\MoneyException;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceDelivery;
use Modules\Invoices\Domain\InvoiceLine;
use Modules\Invoices\Domain\InvoicePayment;
use Modules\Invoices\Domain\InvoicePdf;
use Modules\Invoices\Domain\Invoices;
use Modules\Invoices\Domain\SendInvoice;
use Modules\Projects\Contracts\ProjectDirectory;

/**
 * @phpstan-import-type LineData from Invoices
 */
class InvoiceController extends Controller
{
    public function index(Request $request, CompanyDirectory $companies, ProjectDirectory $projects, TenantContext $tenant): Response
    {
        $open = $request->query('show') === 'open';
        $query = Invoice::query()->orderByRaw("case when status = 'draft' then 0 else 1 end")->orderByDesc('number')->orderByDesc('created_at');
        if ($open) {
            $query->where('kind', 'invoice')->where('status', 'finalized')->whereColumn('paid_minor', '<', 'gross_minor');
        }
        $page = $query->paginate(50)->withQueryString();
        $names = $companies->names(array_values(array_unique(array_map(fn (Invoice $i): string => $i->company_id, $page->items()))));
        $today = CarbonImmutable::parse(now($tenant->current()->timezone)->toDateString(), 'UTC');
        $month = now($tenant->current()->timezone)->startOfMonth();

        return Inertia::render('Invoices::Invoices', [
            'invoices' => ['data' => array_map(fn (Invoice $i): array => [...$this->row($i, $today), 'company' => $names[$i->company_id] ?? ''], $page->items()), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
            'open' => $open,
            'companies' => array_map(fn (array $c): array => ['value' => $c['id'], 'text' => $c['name']], $companies->choices()),
            'projects' => array_map(fn (array $p): array => ['value' => $p['id'], 'text' => "{$p['number']} {$p['name']}"], $projects->active()),
            'from' => $month->copy()->subMonth()->toDateString(),
            'to' => $month->copy()->subDay()->toDateString(),
            'canCreate' => $this->currentUser($request)->can('invoices.create'),
        ]);
    }

    public function show(Request $request, string $invoice, Invoices $invoices, CompanyDirectory $companies, TenantContext $tenant): Response
    {
        $i = Invoice::query()->findOrFail($invoice);
        $user = $this->currentUser($request);
        $preview = $i->isDraft() ? $invoices->preview($i) : null;
        $content = $i->isDraft() ? null : $i->content();
        $cancelled = Invoice::query()->where('cancels_id', $i->id)->first();

        return Inertia::render('Invoices::Invoice', [
            'invoice' => [
                ...$this->row($i, CarbonImmutable::parse(now($tenant->current()->timezone)->toDateString(), 'UTC')),
                'company' => $companies->names([$i->company_id])[$i->company_id] ?? '',
                'service_from' => $i->service_from->toDateString(), 'service_to' => $i->service_to->toDateString(), 'fiscal_reference' => $i->fiscal_reference,
                'lines' => $content['lines'] ?? $i->lines()->get()->map(fn (InvoiceLine $l): array => $l->only(['position', 'description', 'minutes', 'quantity', 'unit', 'unit_price', 'rate_class', 'net_minor']))->all(),
                'groups' => $content['groups'] ?? $preview['totals']->groups ?? [],
                'net_minor' => $i->net_minor, 'tax_minor' => $preview['totals']->taxMinor ?? $i->tax_minor, 'gross_minor' => $i->isDraft() ? ($preview['totals']?->grossMinor() ?? null) : $i->gross_minor,
                'problems' => array_map(fn (string $p): string => __("invoices:problem.{$p}"), $preview['problems'] ?? []),
                'notices' => $content['notices'] ?? array_map(fn (string $n): string => __($n), $preview['notices'] ?? []),
                'cancels' => $i->cancels_id === null ? null : Invoice::query()->findOrFail($i->cancels_id)->only(['id', 'number']),
                'cancelled_by' => $cancelled?->only(['id', 'number']),
                'payments' => InvoicePayment::query()->where('invoice_id', $i->id)->orderBy('paid_on')->orderBy('created_at')->get()->map(fn (InvoicePayment $p): array => ['id' => $p->id, 'paid_on' => $p->paid_on->toDateString(), 'amount_minor' => $p->amount_minor, 'note' => $p->note])->all(),
            ],
            'recipients' => $i->isDraft() ? [] : $companies->invoiceRecipients($i->company_id),
            'deliveries' => InvoiceDelivery::query()->where('invoice_id', $i->id)->orderByDesc('created_at')->limit(20)->get()->map(fn (InvoiceDelivery $d): array => ['id' => $d->id, 'recipient' => $d->recipient, 'status' => $d->status, 'at' => $d->created_at?->toIso8601String()])->all(),
            'confirmed' => (int) now()->timestamp - (is_int($at = $request->session()->get('auth.password_confirmed_at')) ? $at : 0) < config()->integer('auth.password_timeout'),
            'today' => now($tenant->current()->timezone)->toDateString(),
            'can' => ['create' => $user->can('invoices.create'), 'finalize' => $user->can('invoices.finalize'), 'cancel' => $user->can('invoices.cancel'), 'pay' => $user->can('invoices.payments.record')],
        ]);
    }

    public function store(Request $request, Invoices $invoices, CompanyDirectory $companies): RedirectResponse
    {
        $request->validate(['company_id' => ['required', 'ulid']]);
        abort_unless($companies->isActive($request->string('company_id')->toString()), 404);
        [$from, $to, $lines] = $this->content($request);

        return to_route('invoices.show', $invoices->draft($request->string('company_id')->toString(), $from, $to, $lines, $this->currentUser($request))->id);
    }

    public function fromTime(Request $request, Invoices $invoices): RedirectResponse
    {
        $request->validate(['project_id' => ['required', 'ulid'], 'period_from' => ['required', 'date_format:Y-m-d'], 'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from']]);

        return to_route('invoices.show', $invoices->draftFromTime($request->string('project_id')->toString(), $request->string('period_from')->toString(), $request->string('period_to')->toString(), $this->currentUser($request))->id);
    }

    public function update(Request $request, string $invoice, Invoices $invoices): RedirectResponse
    {
        [$from, $to, $lines] = $this->content($request);
        $invoices->change(Invoice::query()->findOrFail($invoice), $from, $to, $lines, $this->currentUser($request));

        return to_route('invoices.show', $invoice);
    }

    public function discard(Request $request, string $invoice, Invoices $invoices): RedirectResponse
    {
        $invoices->discard(Invoice::query()->findOrFail($invoice), $this->currentUser($request));

        return to_route('invoices.index');
    }

    /** Asks for the password (route) and comes back to the invoice. */
    public function confirm(string $invoice): RedirectResponse
    {
        return to_route('invoices.show', Invoice::query()->findOrFail($invoice)->id);
    }

    public function finalize(Request $request, string $invoice, Invoices $invoices): RedirectResponse
    {
        $request->validate(['gross_minor' => ['required', 'integer']]);
        $invoices->finalize(Invoice::query()->findOrFail($invoice), $this->currentUser($request), $request->integer('gross_minor'));

        return to_route('invoices.show', $invoice);
    }

    public function cancel(Request $request, string $invoice, Invoices $invoices): RedirectResponse
    {
        return to_route('invoices.show', $invoices->cancel(Invoice::query()->findOrFail($invoice), $this->currentUser($request))->id);
    }

    public function pay(Request $request, string $invoice, Invoices $invoices): RedirectResponse
    {
        $request->validate(['amount' => ['required', 'string', 'regex:/^-?\d{1,12}([.,]\d{1,3})?\z/'], 'paid_on' => ['required', 'date_format:Y-m-d'], 'note' => ['nullable', 'string', 'max:191']]);
        $model = Invoice::query()->findOrFail($invoice);
        $note = $request->input('note');
        $invoices->pay($model, $request->string('paid_on')->toString(), $this->minor($request->string('amount')->toString(), $model->currency), is_string($note) ? $note : null, $this->currentUser($request));

        return to_route('invoices.show', $invoice);
    }

    /** Mail the issued document to the contacts of the customer that receive invoices. */
    public function send(Request $request, string $invoice, CompanyDirectory $companies, AuditLog $audit): RedirectResponse
    {
        $i = Invoice::query()->findOrFail($invoice);
        abort_if($i->isDraft(), 422);
        $allowed = $companies->invoiceRecipients($i->company_id);
        // Only known contacts, so a document never goes to a mistyped address.
        $request->validate(['recipients' => ['required', 'list', 'min:1', 'max:10'], 'recipients.*' => ['string', 'distinct', Rule::in($allowed)]]);
        $user = $this->currentUser($request);
        foreach (array_filter($request->array('recipients'), is_string(...)) as $address) {
            $delivery = InvoiceDelivery::query()->create(['invoice_id' => $i->id, 'recipient' => $address, 'status' => 'queued', 'sent_by' => $user->id]);
            SendInvoice::dispatch($i->tenant_id, $delivery->id);
        }
        $audit->record('invoices.sent', $i, ['number' => $i->number, 'recipients' => count($request->array('recipients'))], $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('invoices:send.queued')]);

        return to_route('invoices.show', $invoice);
    }

    public function fiscal(Request $request, string $invoice, Invoices $invoices): RedirectResponse
    {
        $request->validate(['fiscal_reference' => ['required', 'string', 'max:120'], 'reason' => ['nullable', 'string', 'max:500']]);
        $reason = $request->input('reason');
        $invoices->setFiscalReference(Invoice::query()->findOrFail($invoice), $request->string('fiscal_reference')->toString(), is_string($reason) ? $reason : null, $this->currentUser($request));

        return to_route('invoices.show', $invoice);
    }

    /** Only issued documents have a PDF: a draft must not look like an invoice. */
    public function pdf(Request $request, string $invoice, InvoicePdf $pdf, AuditLog $audit): HttpResponse
    {
        $i = Invoice::query()->findOrFail($invoice);
        abort_if($i->isDraft(), 404);

        $file = $pdf->render($i);
        $audit->record('invoices.downloaded', $i, ['number' => $i->number], $this->currentUser($request));

        return response($file, 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$i->number}.pdf\"", 'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Invoice $i, CarbonImmutable $today): array
    {
        $open = $i->kind === 'invoice' && $i->status === 'finalized' ? $i->gross_minor - $i->paid_minor : 0;
        $due = $i->due_date === null ? null : CarbonImmutable::parse($i->due_date->toDateString(), 'UTC');

        return [
            ...$i->only(['id', 'kind', 'number', 'currency', 'net_minor', 'gross_minor', 'paid_minor']),
            'status' => match (true) {
                $i->status !== 'finalized' || $i->kind !== 'invoice' => $i->status,
                $open === 0 => 'paid',
                $i->paid_minor > 0 => 'partially_paid',
                default => 'open',
            },
            'open_minor' => $open,
            'issue_date' => $i->issue_date?->toDateString(),
            'due_date' => $due?->toDateString(),
            'overdue_days' => $open > 0 && $due !== null && $due < $today ? (int) $due->diffInDays($today) : 0,
        ];
    }

    private function minor(string $amount, string $currency): int
    {
        try {
            return Money::of(str_replace(',', '.', $amount), Currency::of($currency))->getMinorAmount()->toInt();
        } catch (MoneyException|MathException) {
            throw ValidationException::withMessages(['amount' => __('invoices:errors.amount')]);
        }
    }

    /** @return array{0: string, 1: string, 2: list<LineData>} */
    private function content(Request $request): array
    {
        $number = 'regex:/^-?\d{1,10}([.,]\d{1,4})?\z/';
        /** @var array{service_from: string, service_to: string, lines?: list<LineData>} $data */
        $data = Validator::make($request->all(), [
            'service_from' => ['required', 'date_format:Y-m-d'], 'service_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:service_from'],
            'lines' => ['sometimes', 'list', 'max:200'], 'lines.*' => ['array:description,quantity,unit,unit_price,rate_class'],
            'lines.*.description' => ['required', 'string', 'max:500'], 'lines.*.quantity' => ['required', 'string', $number], 'lines.*.unit' => ['required', Rule::in(['piece', 'hour', 'day', 'flat'])],
            'lines.*.unit_price' => ['required', 'string', $number], 'lines.*.rate_class' => ['required', Rule::in(['standard', 'reduced'])],
        ])->validate();

        return [$data['service_from'], $data['service_to'], array_map(fn (array $l): array => [...$l, 'quantity' => str_replace(',', '.', $l['quantity']), 'unit_price' => str_replace(',', '.', $l['unit_price'])], $data['lines'] ?? [])];
    }
}

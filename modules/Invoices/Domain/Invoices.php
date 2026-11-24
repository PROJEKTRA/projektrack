<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\Audit\AuditLog;
use App\Audit\CanonicalJson;
use App\Documents\Numbers;
use App\Documents\Pdf;
use App\Domain\Money\Amount;
use App\Domain\Tax\TaxCase;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxDecision;
use App\Domain\Tax\TaxEngine;
use App\Domain\Tax\TaxReviewRequired;
use App\Domain\Tax\TaxTotals;
use App\Models\User;
use App\Tenancy\TenantContext;
use Brick\Math\Exception\MathException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Rates\Contracts\TimeValues;
use Modules\Timesheets\Contracts\ApprovedTime;

/**
 * Drafting, finalizing, cancelling and paying invoices (ADR-0004).
 *
 * @phpstan-type LineData array{description: string, quantity: string, unit: string, unit_price: string, rate_class: string}
 */
final class Invoices
{
    public function __construct(
        private readonly CompanyDirectory $companies,
        private readonly ProjectDirectory $projects,
        private readonly ApprovedTime $time,
        private readonly TimeValues $values,
        private readonly TaxEngine $tax,
        private readonly Seller $seller,
        private readonly Numbers $numbers,
        private readonly TenantContext $tenant,
        private readonly AuditLog $audit,
        private readonly Pdf $pdf,
        private readonly EInvoice $einvoice,
    ) {}

    /** @param list<LineData> $lines */
    public function draft(string $companyId, string $from, string $to, array $lines, User $actor): Invoice
    {
        $company = $this->companies->billing($companyId, $to) ?? abort(404);

        return DB::transaction(function () use ($company, $from, $to, $lines, $actor): Invoice {
            $invoice = $this->create($company, null, $from, $to, $company['currency']);
            $this->writeLines($invoice, $lines);
            $this->audit->record('invoices.drafted', $invoice, [], $actor);

            return $invoice->refresh();
        }, 3);
    }

    /** One line per work package and rate, from approved time that no other invoice holds. */
    public function draftFromTime(string $projectId, string $from, string $to, User $actor): Invoice
    {
        $project = $this->projects->projects([$projectId])[$projectId] ?? abort(404);
        if (! in_array($project['billing_model'], ['time_and_material', 'mixed'], true)) {
            throw ValidationException::withMessages(['project_id' => __('invoices:errors.billing_model')]);
        }
        $company = $this->companies->billing($project['company_id'], $to) ?? abort(404);

        return DB::transaction(function () use ($project, $company, $from, $to, $actor): Invoice {
            $invoice = $this->create($company, $project['id'], $from, $to, $project['currency']);
            $all = $this->time->between($project['id'], $from, $to);
            $rows = array_values(array_filter($all, fn (array $r): bool => $r['billable_minutes'] !== 0));
            $held = InvoiceTime::query()->whereIn('active_key', array_column($rows, 'id'))->pluck('active_key')->all();
            $rows = array_values(array_filter($rows, fn (array $r): bool => ! in_array($r['id'], $held, true)));
            if ($rows === []) {
                throw ValidationException::withMessages(['period_from' => __('invoices:errors.no_time')]);
            }
            $this->values->valueMissing($all, $actor);
            $values = $this->values->of(array_column($rows, 'id'));
            $packages = $this->projects->workPackages(array_values(array_unique(array_column($rows, 'work_package_id'))));

            $groups = [];
            foreach ($rows as $row) {
                $value = $values[$row['id']] ?? throw ValidationException::withMessages(['period_from' => __('invoices:errors.no_rate', ['date' => $row['date'], 'user' => $row['user']])]);
                if ($value['currency'] !== $project['currency']) {
                    throw ValidationException::withMessages(['period_from' => __('invoices:errors.currency', ['currency' => $project['currency']])]);
                }
                $key = ($packages[$row['work_package_id']]['code'] ?? '').'|'.$row['work_package_id'].'|'.$value['rate'];
                $groups[$key] ??= ['name' => trim(($packages[$row['work_package_id']]['code'] ?? '').' '.($packages[$row['work_package_id']]['name'] ?? '')), 'rate' => $value['rate'], 'minutes' => 0];
                $groups[$key]['minutes'] += $value['minutes'];
                InvoiceTime::query()->create(['invoice_id' => $invoice->id, 'time_entry_id' => $row['id'], 'active_key' => $row['id']]);
            }
            uksort($groups, strnatcmp(...));

            $position = 0;
            foreach ($groups as $group) {
                if ($group['minutes'] !== 0) {
                    InvoiceLine::query()->create([
                        'invoice_id' => $invoice->id, 'position' => ++$position, 'description' => $group['name'], 'minutes' => $group['minutes'], 'quantity' => null, 'unit' => 'hour',
                        'unit_price' => $group['rate'], 'rate_class' => 'standard', 'net_minor' => Amount::time($group['minutes'], $group['rate'], $project['currency']),
                    ]);
                }
            }
            if ($position === 0) {
                throw ValidationException::withMessages(['period_from' => __('invoices:errors.no_time')]);
            }
            $this->total($invoice);
            $this->audit->record('invoices.drafted', $invoice, ['project' => $project['number'], 'entries' => count($rows)], $actor);

            return $invoice->refresh();
        }, 3);
    }

    /** @param list<LineData> $lines free lines replace free lines; time lines stay */
    public function change(Invoice $invoice, string $from, string $to, array $lines, User $actor): void
    {
        DB::transaction(function () use ($invoice, $from, $to, $lines, $actor): void {
            $locked = $this->locked($invoice->id);
            $this->ensureDraft($locked);
            if (($locked->service_from->toDateString() !== $from || $locked->service_to->toDateString() !== $to) && InvoiceTime::query()->where('invoice_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['service_from' => __('invoices:errors.period_of_time')]);
            }
            $locked->update(['service_from' => $from, 'service_to' => $to]);
            $this->writeLines($locked, $lines);
            $this->audit->record('invoices.draft_changed', $locked, [], $actor);
        }, 3);
    }

    /** Throw a draft away; its time can be billed again and no number was used. */
    public function discard(Invoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $locked = $this->locked($invoice->id);
            $this->ensureDraft($locked);
            InvoiceTime::query()->where('invoice_id', $locked->id)->delete();
            foreach ($locked->lines()->get() as $line) {
                $line->delete();
            }
            $this->audit->record('invoices.discarded', $locked, [], $actor);
            $locked->delete();
        }, 3);
    }

    /**
     * The document as it would be issued on a day, or what is missing.
     *
     * @return array{problems: list<string>, totals: TaxTotals|null, notices: list<string>, decisions: array<string, TaxDecision>}
     */
    public function preview(Invoice $invoice): array
    {
        $seller = $this->seller->data();
        $date = $invoice->service_to->toDateString();
        $company = $this->companies->billing($invoice->company_id, $date);
        $problems = [];
        foreach (['name', 'street', 'postal_code', 'city'] as $field) {
            if ($seller[$field] === '') {
                $problems[] = 'seller_'.$field;
            }
        }
        if ($seller['tax_number'] === '' && $seller['vat_id'] === '') {
            $problems[] = 'seller_tax';
        }
        if ($company === null || $company['address'] === null) {
            $problems[] = 'customer_address';
        } elseif (trim((string) $company['address']['postal_code']) === '' && TaxEngine::needsPostalCode($company['address']['country_code'])) {
            $problems[] = 'customer_postal_code';
        }
        if ($seller['country'] === 'BA' && $seller['vat_status'] === 'registered' && $seller['vat_id'] === '') {
            $problems[] = 'seller_vat_id_ba';
        }
        if ($seller['country'] === 'BA' && ($company['tax']['country_code'] ?? '') === 'BA' && ($company['tax']['tax_status'] ?? '') !== 'private' && ($company['tax']['vat_id'] ?? '') === '' && ($company['tax']['tax_number'] ?? '') === '') {
            $problems[] = 'customer_id_ba';
        }
        if (! $this->pdf->speaks(['invoices:document.', 'invoices:unit.', 'invoices:legal_notice.'], $invoice->locale)) {
            $problems[] = 'language';
        }
        if ($company !== null) {
            array_push($problems, ...EInvoice::missing($seller, $company, $invoice->currency));
        }
        if ($company === null || $company['tax'] === null) {
            return ['problems' => [...$problems, 'customer_tax'], 'totals' => null, 'notices' => [], 'decisions' => []];
        }

        $lines = [];
        $decisions = [];
        $notices = [];
        try {
            foreach ($invoice->lines()->get() as $line) {
                $context = new TaxContext($seller['country'], $seller['vat_status'], $company['tax']['country_code'], $company['tax']['tax_status'], ($company['tax']['vat_id'] ?? '') !== '', $line->rate_class, $date);
                $override = $invoice->override_case === null ? null : TaxCase::tryFrom($invoice->override_case);
                $d = $override === null ? $this->tax->decide($context) : $this->tax->chosen($override, $context);
                $decisions[$line->id] = $d;
                $lines[] = ['decision' => $d, 'net_minor' => $line->net_minor];
                $notice = $d->case->notice();
                if ($notice !== null && ! in_array($notice, $notices, true)) {
                    $notices[] = $notice;
                }
                // Where the recipient is liable under German law, the wording of section 14a(5) UStG.
                if ((($d->case === TaxCase::BaExportService && $company['tax']['country_code'] === 'DE') || $d->case === TaxCase::EuB2bReverseCharge) && ! in_array('invoices:legal_notice.reverse_charge_de', $notices, true)) {
                    $notices[] = 'invoices:legal_notice.reverse_charge_de';
                }
                if ($d->case === TaxCase::EuB2bReverseCharge && $seller['vat_id'] === '') {
                    $problems[] = 'seller_vat_id';
                }
            }
        } catch (TaxReviewRequired $e) {
            return ['problems' => [...$problems, 'tax_'.$e->reason], 'totals' => null, 'notices' => [], 'decisions' => []];
        }
        if ($lines === []) {
            $problems[] = 'no_lines';
        }
        if ($notices !== [] && ! $this->seller->textsConfirmed()) {
            $problems[] = 'texts_not_confirmed';
        }

        $totals = TaxTotals::of($lines);
        if ($totals->grossMinor() < 0) {
            $problems[] = 'negative_total';
        }
        $home = $seller['country'] === 'BA' ? 'BAM' : 'EUR';
        if ($totals->taxMinor !== 0 && $invoice->currency !== $home) {
            $problems[] = 'tax_currency';
        }
        if ($invoice->service_to->toDateString() > now($this->tenant->current()->timezone)->toDateString()) {
            $problems[] = 'future_service';
        }

        return ['problems' => array_values(array_unique($problems)), 'totals' => $totals, 'notices' => $notices, 'decisions' => $decisions];
    }

    /** @param int|null $seenGross what the person saw; anything else is not issued */
    public function finalize(Invoice $invoice, User $actor, ?int $seenGross = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $seenGross): Invoice {
            $locked = $this->locked($invoice->id);
            $this->ensureDraft($locked);
            $preview = $this->preview($locked);
            if ($preview['problems'] !== [] || $preview['totals'] === null) {
                throw ValidationException::withMessages(['invoice' => array_map(fn (string $p): string => __("invoices:problem.{$p}"), $preview['problems'])]);
            }
            if ($seenGross !== null && $seenGross !== $preview['totals']->grossMinor()) {
                throw ValidationException::withMessages(['invoice' => __('invoices:errors.changed')]);
            }
            // Issued documents wait for each other, so numbers and dates never cross.
            $number = $this->numbers->next('invoice', 'INV');
            $today = now($this->tenant->current()->timezone)->toDateString();
            $this->ensureYear($number, $today);
            $company = $this->companies->billing($locked->company_id, $locked->service_to->toDateString());
            $due = CarbonImmutable::parse($today, 'UTC')->addDays($company['payment_days'] ?? 0)->toDateString();
            $totals = $preview['totals'];

            $lines = [];
            foreach ($locked->lines()->get() as $line) {
                $d = $preview['decisions'][$line->id];
                $lines[] = [...$line->only(['position', 'description', 'minutes', 'quantity', 'unit', 'unit_price', 'net_minor']), 'tax_case' => $d->case->value, 'category' => $d->category, 'rate_ppm' => $d->ratePpm, 'rule' => $d->ruleRef];
            }
            // The words of the document language itself, never of a fallback.
            $legal = $this->pdf->labels(['invoices:legal_notice.'], $locked->locale);
            $this->seal($locked, [
                'kind' => 'invoice', 'number' => $number, 'issue_date' => $today, 'due_date' => $due, 'service_from' => $locked->service_from->toDateString(), 'service_to' => $locked->service_to->toDateString(),
                'currency' => $locked->currency, 'locale' => $locked->locale, 'seller' => $this->seller->data(), 'buyer' => $company, 'lines' => $lines, 'groups' => $totals->groups,
                'net_minor' => $totals->netMinor, 'tax_minor' => $totals->taxMinor, 'gross_minor' => $totals->grossMinor(),
                'override' => $locked->override_case === null ? null : ['case' => $locked->override_case, 'reason' => $locked->override_reason, 'by' => $locked->override_by, 'original' => $this->originalCases($locked)],
                'labels' => $this->pdf->labels(['invoices:document.', 'invoices:unit.'], $locked->locale), 'format' => $this->pdf->format($locked->locale)->locale,
                // Two notices can read the same in one language.
                'notices' => array_values(array_unique(array_map(fn (string $id): string => $legal[$id] ?? throw ValidationException::withMessages(['invoice' => __('invoices:problem.language')]), $preview['notices']))),
            ], $actor);
            $this->audit->record('invoices.finalized', $locked, ['number' => $number, 'gross_minor' => $locked->gross_minor, 'hash' => $locked->content_hash], $actor);

            return $locked;
        }, 3);
    }

    /** A document of its own that takes the whole invoice back. */
    public function cancel(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $locked = $this->locked($invoice->id);
            if ($locked->kind !== 'invoice' || $locked->status !== 'finalized') {
                throw ValidationException::withMessages(['invoice' => __('invoices:errors.not_cancellable')]);
            }
            if ($locked->credited_minor !== 0) {
                throw ValidationException::withMessages(['invoice' => __('invoices:errors.credited')]);
            }
            if ($locked->paid_minor !== 0) {
                throw ValidationException::withMessages(['invoice' => __('invoices:errors.paid')]);
            }
            $content = $locked->content();
            $number = $this->numbers->next('cancellation', 'CN');
            $today = now($this->tenant->current()->timezone)->toDateString();
            $this->ensureYear($number, $today);
            $negate = fn (array $rows): array => array_map(fn (mixed $row): array => array_map(fn (mixed $v): mixed => $v, $this->negated(is_array($row) ? $row : [])), $rows);

            $cancellation = Invoice::query()->create([
                'kind' => 'cancellation', 'status' => 'draft', 'company_id' => $locked->company_id, 'project_id' => $locked->project_id, 'cancels_id' => $locked->id,
                'service_from' => $locked->service_from, 'service_to' => $locked->service_to, 'currency' => $locked->currency, 'locale' => $locked->locale,
            ]);
            $this->seal($cancellation, [
                ...$content, 'kind' => 'cancellation', 'number' => $number, 'cancels' => $locked->number, 'cancels_date' => $locked->issue_date?->toDateString(), 'issue_date' => $today, 'due_date' => $today,
                'lines' => $negate(is_array($content['lines'] ?? null) ? $content['lines'] : []), 'groups' => $negate(is_array($content['groups'] ?? null) ? $content['groups'] : []),
                'net_minor' => -$locked->net_minor, 'tax_minor' => -$locked->tax_minor, 'gross_minor' => -$locked->gross_minor,
            ], $actor);
            $locked->update(['status' => 'cancelled']);
            // The time can be billed again; which invoice billed it first stays visible.
            InvoiceTime::query()->where('invoice_id', $locked->id)->update(['active_key' => null]);
            $this->audit->record('invoices.cancelled', $locked, ['number' => $locked->number, 'cancellation' => $number, 'hash' => $cancellation->content_hash], $actor);

            return $cancellation;
        }, 3);
    }

    /** Choose another tax case for a draft, or (null) go back to the rules. */
    public function override(Invoice $invoice, ?TaxCase $case, string $reason, User $actor): void
    {
        DB::transaction(function () use ($invoice, $case, $reason, $actor): void {
            $locked = $this->locked($invoice->id);
            $this->ensureDraft($locked);
            if ($case !== null && ! in_array($case, TaxEngine::choices($this->seller->data()['country']), true)) {
                throw ValidationException::withMessages(['case' => __('invoices:errors.case')]);
            }
            $original = $this->originalCases($locked);
            $locked->update(['override_case' => $case?->value, 'override_reason' => $case === null ? null : $reason, 'override_by' => $case === null ? null : $actor->id]);
            $this->audit->record('invoices.tax_overridden', $locked, ['case' => $case?->value, 'original' => $original, 'reason' => $reason], $actor);
        }, 3);
    }

    /**
     * Give back part of an issued invoice, with the tax of its lines.
     *
     * @param  array<int, int>  $credits  line position => net minor units to give back
     */
    public function credit(Invoice $invoice, array $credits, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $credits, $reason, $actor): Invoice {
            $locked = $this->locked($invoice->id);
            if ($locked->kind !== 'invoice' || $locked->status !== 'finalized') {
                throw ValidationException::withMessages(['lines' => __('invoices:errors.not_cancellable')]);
            }
            $content = $locked->content();
            $given = [];
            $done = [];
            foreach (Invoice::query()->where('credits_id', $locked->id)->get() as $earlier) {
                $note = $earlier->content();
                foreach ($this->rows($note['lines'] ?? null) as $row) {
                    $given[$this->int($row, 'position')] = ($given[$this->int($row, 'position')] ?? 0) - $this->int($row, 'net_minor');
                }
                foreach ($this->rows($note['groups'] ?? null) as $group) {
                    $key = $this->groupKey($group);
                    $done[$key]['net'] = ($done[$key]['net'] ?? 0) - $this->int($group, 'net_minor');
                    $done[$key]['tax'] = ($done[$key]['tax'] ?? 0) - $this->int($group, 'tax_minor');
                }
            }
            $lines = [];
            $now = [];
            foreach ($this->rows($content['lines'] ?? null) as $row) {
                $position = $this->int($row, 'position');
                $amount = $credits[$position] ?? 0;
                unset($credits[$position]);
                if ($amount === 0) {
                    continue;
                }
                if ($amount < 0 || $amount > $this->int($row, 'net_minor') - ($given[$position] ?? 0)) {
                    throw ValidationException::withMessages(["lines.{$position}" => __('invoices:errors.credit_amount')]);
                }
                $lines[] = [...$row, 'minutes' => null, 'quantity' => '-1.0000', 'unit' => 'flat', 'unit_price' => Amount::decimal($amount, $locked->currency), 'net_minor' => -$amount];
                $key = is_string($row['tax_case'] ?? null) ? $this->groupKey([...$row, 'case' => $row['tax_case']]) : '';
                $now[$key] = ($now[$key] ?? 0) + $amount;
            }
            if ($lines === [] || $credits !== []) {
                throw ValidationException::withMessages(['lines' => __('invoices:errors.credit_empty')]);
            }
            // Per tax group never more than the invoice holds (discount lines count), and the
            // tax of all notes together is rounded once, so the last note ends at zero.
            $groups = [];
            foreach ($this->rows($content['groups'] ?? null) as $group) {
                $key = $this->groupKey($group);
                $net = $now[$key] ?? 0;
                if ($net === 0) {
                    continue;
                }
                $total = ($done[$key]['net'] ?? 0) + $net;
                if ($total > $this->int($group, 'net_minor')) {
                    throw ValidationException::withMessages(['lines' => __('invoices:errors.credit_amount')]);
                }
                $tax = $total === $this->int($group, 'net_minor') ? $this->int($group, 'tax_minor') : Amount::share($total, $this->int($group, 'rate_ppm'));
                $groups[] = [...$group, 'net_minor' => -$net, 'tax_minor' => -($tax - ($done[$key]['tax'] ?? 0))];
            }
            $sumNet = array_sum(array_column($groups, 'net_minor'));
            $sumTax = array_sum(array_column($groups, 'tax_minor'));
            $number = $this->numbers->next('cancellation', 'CN');
            $today = now($this->tenant->current()->timezone)->toDateString();
            $this->ensureYear($number, $today);
            $note = Invoice::query()->create([
                'kind' => 'credit_note', 'status' => 'draft', 'company_id' => $locked->company_id, 'project_id' => $locked->project_id, 'credits_id' => $locked->id,
                'service_from' => $locked->service_from, 'service_to' => $locked->service_to, 'currency' => $locked->currency, 'locale' => $locked->locale,
            ]);
            $this->seal($note, [
                ...$content, 'kind' => 'credit_note', 'number' => $number, 'cancels' => $locked->number, 'cancels_date' => $locked->issue_date?->toDateString(), 'issue_date' => $today, 'due_date' => $today, 'reason' => $reason,
                'lines' => $lines, 'groups' => $groups, 'net_minor' => $sumNet, 'tax_minor' => $sumTax, 'gross_minor' => $sumNet + $sumTax,
            ], $actor);
            $before = $locked->openMinor();
            $locked->update(['credited_minor' => $locked->credited_minor + $sumNet + $sumTax]);
            $this->announcePaid($locked, $before);
            $this->audit->record('invoices.credited', $locked, ['number' => $locked->number, 'credit_note' => $number, 'gross_minor' => $sumNet + $sumTax, 'reason' => $reason, 'hash' => $note->content_hash], $actor);

            return $note;
        }, 3);
    }

    /** @return list<string> what the rules decide for the lines, or why they refuse */
    private function originalCases(Invoice $invoice): array
    {
        $seller = $this->seller->data();
        $date = $invoice->service_to->toDateString();
        $tax = $this->companies->billing($invoice->company_id, $date)['tax'] ?? null;
        $cases = [];
        foreach ($tax === null ? [] : $invoice->lines()->get() as $line) {
            try {
                $cases[] = $this->tax->decide(new TaxContext($seller['country'], $seller['vat_status'], $tax['country_code'], $tax['tax_status'], ($tax['vat_id'] ?? '') !== '', $line->rate_class, $date))->case->value;
            } catch (TaxReviewRequired $e) {
                $cases[] = 'review:'.$e->reason;
            }
        }

        return array_values(array_unique($cases));
    }

    /** Told when the rest was paid, not when nothing was ever paid. A return and a new payment tell it again. */
    private function announcePaid(Invoice $invoice, int $openBefore): void
    {
        if ($openBefore > 0 && $invoice->openMinor() <= 0 && $invoice->paid_minor > 0) {
            DB::afterCommit(fn () => event(new InvoicePaid($invoice->id)));
        }
    }

    /** @param array<array-key, mixed> $group */
    private function groupKey(array $group): string
    {
        return implode('|', array_map(fn (string $k): string => is_scalar($group[$k] ?? null) ? (string) $group[$k] : '', ['case', 'category', 'rate_ppm']));
    }

    /** @param array<array-key, mixed> $row */
    private function int(array $row, string $key): int
    {
        return is_int($row[$key] ?? null) ? $row[$key] : 0;
    }

    /** @return list<array<array-key, mixed>> */
    private function rows(mixed $value): array
    {
        return array_values(array_filter(is_array($value) ? $value : [], is_array(...)));
    }

    public function pay(Invoice $invoice, string $paidOn, int $amountMinor, ?string $note, User $actor): void
    {
        DB::transaction(function () use ($invoice, $paidOn, $amountMinor, $note, $actor): void {
            $locked = $this->locked($invoice->id);
            if ($locked->kind !== 'invoice' || $locked->status !== 'finalized') {
                throw ValidationException::withMessages(['amount' => __('invoices:errors.not_payable')]);
            }
            if ($paidOn > now($this->tenant->current()->timezone)->toDateString()) {
                throw ValidationException::withMessages(['paid_on' => __('invoices:errors.future')]);
            }
            $paid = $locked->paid_minor + $amountMinor;
            if ($amountMinor === 0 || $paid < 0 || ($amountMinor > 0 && $paid > $locked->gross_minor + $locked->credited_minor)) {
                throw ValidationException::withMessages(['amount' => __('invoices:errors.amount')]);
            }
            InvoicePayment::query()->create(['invoice_id' => $locked->id, 'paid_on' => $paidOn, 'amount_minor' => $amountMinor, 'note' => $note, 'recorded_by' => $actor->id]);
            $before = $locked->openMinor();
            $locked->update(['paid_minor' => $paid]);
            $this->audit->record('invoices.payment_recorded', $locked, ['number' => $locked->number, 'amount_minor' => $amountMinor], $actor);
            $this->announcePaid($locked, $before);
        }, 3);
    }

    public function setFiscalReference(Invoice $invoice, string $reference, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($invoice, $reference, $reason, $actor): void {
            $locked = $this->locked($invoice->id);
            abort_if($locked->isDraft(), 422);
            if ($locked->fiscal_reference !== null && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => __('invoices:errors.fiscal_reason')]);
            }
            $this->audit->record('invoices.fiscal_reference', $locked, ['number' => $locked->number, 'from' => $locked->fiscal_reference, 'to' => $reference, 'reason' => $reason], $actor);
            $locked->update(['fiscal_reference' => $reference]);
        }, 3);
    }

    /** @param array{id: string, currency: string, document_locale: string, ...} $company */
    private function create(array $company, ?string $projectId, string $from, string $to, string $currency): Invoice
    {
        if (! Amount::isCurrency($currency)) {
            throw ValidationException::withMessages(['currency' => __('invoices:errors.unknown_currency')]);
        }

        return Invoice::query()->create(['kind' => 'invoice', 'status' => 'draft', 'company_id' => $company['id'], 'project_id' => $projectId, 'service_from' => $from, 'service_to' => $to, 'currency' => $currency, 'locale' => $company['document_locale']]);
    }

    /** @param list<LineData> $lines */
    private function writeLines(Invoice $invoice, array $lines): void
    {
        foreach ($invoice->lines()->whereNull('minutes')->get() as $old) {
            $old->delete();
        }
        $position = (int) $invoice->lines()->max('position');
        foreach ($lines as $line) {
            try {
                $net = Amount::line($line['quantity'], $line['unit_price'], $invoice->currency);
            } catch (MathException) {
                $net = PHP_INT_MAX;
            }
            if (abs($net) > 1_000_000_000_000) {
                throw ValidationException::withMessages(['lines' => __('invoices:errors.too_large')]);
            }
            InvoiceLine::query()->create([...$line, 'invoice_id' => $invoice->id, 'position' => ++$position, 'minutes' => null, 'net_minor' => $net]);
        }
        $this->total($invoice);
    }

    private function total(Invoice $invoice): void
    {
        $invoice->update(['net_minor' => (int) $invoice->lines()->sum('net_minor')]);
    }

    /** @param array<array-key, mixed> $content */
    private function seal(Invoice $invoice, array $content, User $actor): void
    {
        // Part of the sealed content, so the hash covers it too.
        if (is_array($content['buyer'] ?? null) && in_array($content['buyer']['einvoice_format'] ?? null, ['zugferd', 'xrechnung_cii'], true)) {
            $content['xml'] = $this->einvoice->write($content);
        }
        $snapshot = CanonicalJson::encode($content);
        $invoice->update([
            'status' => 'finalized', 'number' => $content['number'], 'issue_date' => $content['issue_date'], 'due_date' => $content['due_date'],
            'net_minor' => $content['net_minor'], 'tax_minor' => $content['tax_minor'], 'gross_minor' => $content['gross_minor'],
            'snapshot' => $snapshot, 'content_hash' => hash('sha256', $snapshot), 'finalized_at' => now(), 'finalized_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @return array<array-key, mixed>
     */
    private function negated(array $row): array
    {
        foreach (['minutes', 'net_minor', 'tax_minor'] as $key) {
            if (is_int($row[$key] ?? null)) {
                $row[$key] = -$row[$key];
            }
        }
        if (is_string($row['quantity'] ?? null)) {
            $row['quantity'] = str_starts_with($row['quantity'], '-') ? substr($row['quantity'], 1) : '-'.$row['quantity'];
        }

        return $row;
    }

    private function locked(string $id): Invoice
    {
        return Invoice::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** Number and date were taken around midnight of a new year: try again. */
    private function ensureYear(string $number, string $today): void
    {
        if (substr($today, 0, 4) !== (explode('-', $number)[1] ?? '')) {
            throw ValidationException::withMessages(['invoice' => __('invoices:errors.changed')]);
        }
    }

    private function ensureDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => __('invoices:errors.finalized')]);
        }
    }
}

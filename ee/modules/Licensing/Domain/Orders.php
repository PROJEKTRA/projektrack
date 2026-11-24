<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

namespace Modules\Licensing\Domain;

use App\Audit\AuditLog;
use App\Authorization\Permissions;
use App\Documents\Numbers;
use App\Documents\Pdf;
use App\I18n\Strings;
use App\Licensing\InvalidLicense;
use App\Licensing\LicenseKeys;
use App\Licensing\LicenseToken;
use App\Models\User;
use App\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Invoices\Contracts\InvoiceDrafts;
use Throwable;

/**
 * From an accepted order to an active license. The payload is signed offline
 * (ee/licensing/issue.php) and the license is entered here again.
 */
final class Orders
{
    private const int PENDING_DAYS = 14;

    public function __construct(
        private readonly InvoiceDrafts $invoices,
        private readonly Numbers $numbers,
        private readonly TenantContext $tenant,
        private readonly AuditLog $audit,
        private readonly Permissions $permissions,
        private readonly LicenseKeys $keys,
        private readonly CompanyDirectory $companies,
        private readonly Pdf $pdf,
        private readonly Strings $strings,
    ) {}

    /** @param array{full_seats: int, light_seats: int, instance_id: string, domain: string, starts_on: string, customer_name: string} $data */
    public function confirm(LicensePlan $plan, string $companyId, array $data, User $actor): LicenseOrder
    {
        return DB::transaction(function () use ($plan, $companyId, $data, $actor): LicenseOrder {
            // Two confirmations of one instance wait for each other.
            LicensePlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if (LicenseOrder::query()->where('instance_id', $data['instance_id'])->whereIn('status', ['pending_payment', 'paid', 'review'])->exists()) {
                throw ValidationException::withMessages(['instance_id' => __('licensing:errors.open_order')]);
            }
            $start = CarbonImmutable::parse($data['starts_on'], 'UTC');
            $order = new LicenseOrder([
                ...$data, 'license_plan_id' => $plan->id, 'company_id' => $companyId, 'status' => 'pending_payment', 'serial' => 1,
                'license_id' => 'lic_'.strtoupper((string) Str::ulid()), 'ends_on' => self::lastDay($start, $plan->term_months), 'pending_until' => now()->addDays(self::PENDING_DAYS)->endOfDay(),
                ...$plan->only(['full_price', 'light_price', 'onboarding_price', 'currency']), 'price_locked_until' => $plan->price_lock_months === 0 ? $start->toDateString() : self::lastDay($start, $plan->price_lock_months),
            ]);
            $order->fill($this->invoice($order, $plan, $actor));
            $order->number = $this->numbers->next('license_order', 'LO');
            $order->save();
            $this->log($order, 'confirmed', $actor);

            return $order;
        }, 3);
    }

    /** The last day of a term: a term from the 31st ends with a shorter month. */
    public static function lastDay(CarbonImmutable $start, int $months): string
    {
        $end = $start->addMonthsNoOverflow($months);

        return ($end->day === $start->day ? $end->subDay() : $end)->toDateString();
    }

    /** A new draft at the agreed prices after the invoice was cancelled. */
    public function invoiceAgain(LicenseOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $locked = $this->locked($order->id);
            if (! in_array($locked->status, ['pending_payment', 'review'], true) || ($this->state($locked)['status'] ?? '') !== 'cancelled') {
                throw ValidationException::withMessages(['order' => __('licensing:errors.state')]);
            }
            $locked->update([...$this->invoice($locked, LicensePlan::query()->findOrFail($locked->license_plan_id), $actor), 'status' => 'pending_payment']);
            $this->log($locked, 'invoiced', $actor);
        }, 3);
    }

    /** An issued invoice is cancelled first; a draft is free to be thrown away then. */
    public function cancel(LicenseOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $locked = $this->locked($order->id);
            $invoice = $this->state($locked)['status'] ?? 'draft';
            if ($locked->status === 'cancelled' || $invoice === 'finalized') {
                throw ValidationException::withMessages(['order' => __('licensing:errors.cancel_invoice')]);
            }
            $locked->update(['status' => 'cancelled', ...($invoice === 'draft' ? ['invoice_id' => null] : [])]);
            $this->log($locked, 'cancelled', $actor);
        }, 3);
    }

    /**
     * What is signed offline (ADR-0003 section 3).
     *
     * @return array<string, mixed>
     */
    public function payload(LicenseOrder $order): array
    {
        $active = in_array($order->status, ['paid', 'active'], true);
        if (in_array($order->status, ['cancelled', 'review'], true) || ($active && ! $this->matches($order))) {
            throw ValidationException::withMessages(['order' => __('licensing:errors.review')]);
        }
        $plan = LicensePlan::query()->findOrFail($order->license_plan_id);

        return [
            'v' => 1, 'iss' => 'projektra-licensing', 'license_id' => $order->license_id, 'serial' => $order->serial,
            'customer' => ['id' => $order->number, 'name' => $order->customer_name],
            'edition' => $plan->edition, 'plan' => $plan->key, 'seats' => ['full' => $order->full_seats, 'light' => $order->light_seats],
            'binding' => ['instance_id' => $order->instance_id, 'domains' => [$order->domain]], 'features' => [], 'addons' => [],
            'status' => $active ? 'active' : 'pending_payment', ...($active ? [] : ['status_until' => $order->pending_until?->toIso8601ZuluString()]),
            'not_before' => $order->starts_on->format('Y-m-d').'T00:00:00Z', 'expires_at' => $order->ends_on->copy()->addDay()->format('Y-m-d').'T00:00:00Z',
            'grace' => ['expiry_days' => 14, 'over_seat_days' => 30, 'pending_payment_days' => self::PENDING_DAYS], 'check_in' => ['interval_hours' => 24, 'required' => false],
        ];
    }

    /** The signed license comes back; it must be exactly the one of the order. */
    public function enter(LicenseOrder $order, string $token, User $actor): void
    {
        try {
            $license = LicenseToken::decode($token, $this->keys);
        } catch (InvalidLicense) {
            throw ValidationException::withMessages(['token' => __('licensing:errors.token')]);
        }
        DB::transaction(function () use ($order, $token, $license, $actor): void {
            $locked = $this->locked($order->id);
            $w = $this->payload($locked);
            $has = [
                $license->id, $license->serial, $license->customer, $license->edition->value, $license->plan, $license->fullSeats, $license->lightSeats, $license->instanceId, $license->domains, $license->features, $license->addons,
                $license->declaredStatus, $license->statusUntil?->toIso8601ZuluString(), $license->notBefore->toIso8601ZuluString(), $license->expiresAt->toIso8601ZuluString(), $license->expiryGraceDays,
            ];
            $wanted = [
                $locked->license_id, $locked->serial, $locked->customer_name, $w['edition'], $w['plan'], $locked->full_seats, $locked->light_seats, $locked->instance_id, [$locked->domain], [], [],
                $w['status'], $w['status_until'] ?? null, $w['not_before'], $w['expires_at'], 14,
            ];
            // One license per serial: the customer's instance refuses a second one too.
            if ($has !== $wanted || ($locked->issued_serial === $locked->serial && $locked->token !== trim($token))) {
                throw ValidationException::withMessages(['token' => __('licensing:errors.other')]);
            }
            $locked->update(['token' => trim($token), 'issued_serial' => $locked->serial, 'status' => $locked->status === 'paid' ? 'active' : $locked->status]);
            $this->log($locked, $locked->status === 'active' ? 'activated' : 'issued', $actor);
        }, 3);
    }

    public function downloaded(LicenseOrder $order, User $actor): void
    {
        $this->log($order, 'downloaded', $actor);
    }

    /**
     * Payment arrived: a new issue with the next serial is due. Safe to be told
     * twice. Anything but the agreed invoice, paid in full, is for a person.
     */
    public function paid(string $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId): void {
            $order = LicenseOrder::query()->where('invoice_id', $invoiceId)->whereIn('status', ['pending_payment', 'review'])->lockForUpdate()->first();
            if ($order === null) {
                return;
            }
            if (! $this->matches($order)) {
                $this->review($order);

                return;
            }
            $order->update(['status' => 'paid', 'serial' => $order->serial + 1]);
            $this->log($order, 'paid', null);
            DB::afterCommit(fn () => $this->tell('licensing:notice.paid', $order));
        }, 3);
    }

    /**
     * Daily: payments nobody told about, money that was given back, and orders
     * whose grace ended unpaid (told once).
     */
    public function check(): int
    {
        $told = 0;
        foreach (LicenseOrder::query()->whereIn('status', ['pending_payment', 'paid', 'active'])->get() as $order) {
            $invoice = $this->state($order);
            if ($order->status !== 'pending_payment') {
                // A term that ended is history.
                if (! $this->matches($order) && $order->ends_on->toDateString() >= $this->today()) {
                    DB::transaction(fn () => $this->review($this->locked($order->id)));
                    $told++;
                }
            } elseif ($invoice !== null && $invoice['settled']) {
                $this->paid((string) $order->invoice_id);
            } elseif ($order->reminded_at === null && $order->pending_until?->isPast() === true && ($invoice['due_date'] ?? '') < $this->today()) {
                DB::transaction(function () use ($order): void {
                    $order->update(['reminded_at' => now()]);
                    $this->log($order, 'overdue', null);
                });
                $this->tell('licensing:notice.overdue', $order);
                $told++;
            }
        }

        return $told;
    }

    /** @return array{status: string, net_minor: int, credited_minor: int, settled: bool, due_date: string|null}|null */
    private function state(LicenseOrder $order): ?array
    {
        return $order->invoice_id === null ? null : $this->invoices->state($order->invoice_id);
    }

    /** The agreed amount was invoiced and paid, and nothing given back. */
    private function matches(LicenseOrder $order): bool
    {
        $invoice = $this->state($order);

        return $invoice !== null && $invoice['settled'] && $invoice['net_minor'] === $order->net_minor;
    }

    private function review(LicenseOrder $order): void
    {
        if ($order->status === 'review') {
            return;
        }
        // The next issue gets a serial of its own.
        $order->update(['status' => 'review']);
        $this->log($order, 'review', null);
        DB::afterCommit(fn () => $this->tell('licensing:notice.review', $order));
    }

    /** @return array{invoice_id: string, net_minor: int} */
    private function invoice(LicenseOrder $order, LicensePlan $plan, User $actor): array
    {
        $today = $this->today();
        $locale = $this->companies->billing($order->company_id, $today)['document_locale'] ?? abort(404);
        if (! $this->pdf->speaks(['licensing:line.'], $locale)) {
            throw ValidationException::withMessages(['company_id' => __('licensing:errors.language')]);
        }
        $format = $this->pdf->format($locale);
        $args = ['from' => $format->day($order->starts_on->toDateString()), 'to' => $format->day($order->ends_on->toDateString())];
        $lines = [];
        foreach ([['full', $order->full_seats, $order->full_price], ['light', $order->light_seats, $order->light_price], ['onboarding', 1, $order->onboarding_price]] as [$key, $quantity, $price]) {
            if ($quantity > 0 && BigDecimal::of($price)->isPositive()) {
                $text = $this->strings->get("licensing:line.{$key}", $args, $locale);
                $lines[] = ['description' => "{$plan->name}: {$text}", 'quantity' => (string) $quantity, 'unit' => 'piece', 'unit_price' => $price, 'rate_class' => 'standard'];
            }
        }
        // The license is delivered today: that is the day of supply on the invoice.
        $id = $this->invoices->draft($order->company_id, $today, $today, $lines, $actor);
        // An order whose invoice could never be issued is not confirmed.
        $never = array_filter($this->invoices->problems($id), fn (string $key): bool => preg_match('/^(tax_|customer_|language|negative_total)/', $key) === 1, ARRAY_FILTER_USE_KEY);
        if ($never !== []) {
            throw ValidationException::withMessages(['company_id' => array_values($never)]);
        }

        return ['invoice_id' => $id, 'net_minor' => $this->invoices->state($id)['net_minor'] ?? 0];
    }

    private function today(): string
    {
        return now($this->tenant->current()->timezone)->toDateString();
    }

    private function locked(string $id): LicenseOrder
    {
        return LicenseOrder::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** A mail server that is down never breaks a payment or the daily check. */
    private function tell(string $message, LicenseOrder $order): void
    {
        $managers = User::query()->where('user_type', '!=', 'portal')->get()->filter(fn (User $u): bool => $this->permissions->allows($u, 'licensing.subscriptions.manage'));
        foreach ($managers as $user) {
            try {
                $user->notify(new OrderNotice($message, $order->number, $order->id));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function log(LicenseOrder $order, string $event, ?User $actor): void
    {
        LicenseOrderEvent::query()->create(['license_order_id' => $order->id, 'event' => $event, 'actor_id' => $actor?->id]);
        $this->audit->record("licensing.order.{$event}", $order, ['number' => $order->number, 'serial' => $order->serial], $actor);
    }
}

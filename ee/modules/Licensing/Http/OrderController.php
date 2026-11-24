<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

namespace Modules\Licensing\Http;

use App\Audit\AuditLog;
use App\Domain\Money\Amount;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Invoices\Contracts\InvoiceDrafts;
use Modules\Licensing\Domain\LicenseOrder;
use Modules\Licensing\Domain\LicenseOrderEvent;
use Modules\Licensing\Domain\LicensePlan;
use Modules\Licensing\Domain\Orders;

class OrderController extends Controller
{
    public function index(Request $request, CompanyDirectory $companies, TenantContext $tenant): Response
    {
        $orders = LicenseOrder::query()->orderByDesc('number')->paginate(50);
        $names = $companies->names(array_values(array_unique(array_map(fn (LicenseOrder $o): string => $o->company_id, $orders->items()))));
        $user = $this->currentUser($request);

        return Inertia::render('Licensing::Orders', [
            'orders' => array_map(fn (LicenseOrder $o): array => [...$o->only(['id', 'number', 'status', 'full_seats', 'light_seats']), 'company' => $names[$o->company_id] ?? '', 'ends_on' => $o->ends_on->toDateString()], $orders->items()),
            'plans' => LicensePlan::query()->orderBy('name')->get()->map(fn (LicensePlan $p): array => $p->only(['id', 'key', 'name', 'edition', 'term_months', 'price_lock_months', 'full_price', 'light_price', 'onboarding_price', 'currency', 'active']))->all(),
            'companies' => array_map(fn (array $c): array => ['value' => $c['id'], 'text' => $c['name']], $companies->choices()),
            'today' => now($tenant->current()->timezone)->toDateString(),
            'can' => ['plans' => $user->can('licensing.plans.manage'), 'orders' => $user->can('licensing.subscriptions.manage')],
        ]);
    }

    public function plan(Request $request, TenantContext $tenant, AuditLog $audit): RedirectResponse
    {
        $price = ['required', 'string', 'regex:/^\d{1,10}([.,]\d{1,4})?\z/'];
        /** @var array<string, string|int|bool> $data */
        $data = Validator::make($request->all(), [
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,59}\z/', Rule::unique('license_plans', 'key')->where('tenant_id', $tenant->id())],
            'name' => ['required', 'string', 'max:120'], 'edition' => ['required', Rule::in(['business', 'enterprise', 'partner'])],
            'term_months' => ['required', 'integer', 'between:1,60'], 'price_lock_months' => ['required', 'integer', 'between:0,120'],
            'full_price' => $price, 'light_price' => $price, 'onboarding_price' => $price,
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}\z/', fn (string $attribute, mixed $value, callable $fail) => is_string($value) && Amount::isCurrency($value) ?: $fail(__('licensing:errors.currency'))],
        ])->validate();
        foreach (['full_price', 'light_price', 'onboarding_price'] as $key) {
            $data[$key] = str_replace(',', '.', (string) $data[$key]);
        }
        if (! BigDecimal::of($data['full_price'])->isPositive()) {
            throw ValidationException::withMessages(['full_price' => __('licensing:errors.price')]);
        }
        $plan = LicensePlan::query()->create([...$data, 'active' => true]);
        $audit->record('licensing.plan.added', $plan, $data, $this->currentUser($request));

        return to_route('licensing.index');
    }

    /** A plan that is no longer sold; its orders keep it. */
    public function retire(Request $request, string $plan, AuditLog $audit): RedirectResponse
    {
        $model = LicensePlan::query()->findOrFail($plan);
        $model->update(['active' => ! $model->active]);
        $audit->record($model->active ? 'licensing.plan.offered' : 'licensing.plan.retired', $model, [], $this->currentUser($request));

        return to_route('licensing.index');
    }

    public function store(Request $request, Orders $orders, CompanyDirectory $companies, TenantContext $tenant): RedirectResponse
    {
        $today = now($tenant->current()->timezone)->toDateString();
        /** @var array{plan_id: string, company_id: string, full_seats: int|string, light_seats: int|string, instance_id: string, domain: string, starts_on: string, customer_name: string} $data */
        $data = Validator::make($request->all(), [
            'plan_id' => ['required', 'ulid'], 'company_id' => ['required', 'ulid'],
            'full_seats' => ['required', 'integer', 'between:1,100000'], 'light_seats' => ['required', 'integer', 'between:0,100000'],
            'instance_id' => ['required', 'string', 'regex:/^inst_[0-9A-Z]{26}\z/'], 'domain' => ['required', 'string', 'max:253', 'regex:/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]\z/'],
            // Not in the future: how prepaid terms are invoiced is open (docs/decisions).
            'starts_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today, 'after_or_equal:'.CarbonImmutable::parse($today, 'UTC')->subDays(31)->toDateString()],
            'customer_name' => ['required', 'string', 'max:200', fn (string $attribute, mixed $value, callable $fail) => is_string($value) && strlen($value) <= 255 ?: $fail(__('licensing:errors.name'))],
        ])->validate();
        $plan = LicensePlan::query()->where('active', true)->findOrFail(strtolower($data['plan_id']));
        $company = strtolower($data['company_id']);
        abort_unless($companies->isActive($company), 404);
        if (($companies->billing($company, $data['starts_on'])['currency'] ?? '') !== $plan->currency) {
            throw ValidationException::withMessages(['company_id' => __('licensing:errors.company_currency', ['currency' => $plan->currency])]);
        }
        $order = $orders->confirm($plan, $company, ['full_seats' => (int) $data['full_seats'], 'light_seats' => (int) $data['light_seats'], 'instance_id' => $data['instance_id'], 'domain' => $data['domain'], 'starts_on' => $data['starts_on'], 'customer_name' => $data['customer_name']], $this->currentUser($request));

        return to_route('licensing.show', $order->id);
    }

    public function show(Request $request, string $order, CompanyDirectory $companies, InvoiceDrafts $invoices): Response
    {
        $o = LicenseOrder::query()->findOrFail($order);
        $manage = $this->currentUser($request)->can('licensing.subscriptions.manage');
        $state = $o->invoice_id === null ? null : $invoices->state($o->invoice_id);

        return Inertia::render('Licensing::Order', [
            'order' => [...$o->only(['id', 'number', 'status', 'license_id', 'serial', 'full_seats', 'light_seats', 'instance_id', 'domain', 'invoice_id']), 'company' => $companies->names([$o->company_id])[$o->company_id] ?? '', 'token' => $manage ? $o->token : null, 'price_locked_until' => $o->price_locked_until->toDateString(), 'starts_on' => $o->starts_on->toDateString(), 'ends_on' => $o->ends_on->toDateString(), 'pending_until' => $o->pending_until?->toIso8601String()],
            'plan' => LicensePlan::query()->findOrFail($o->license_plan_id)->name,
            'events' => LicenseOrderEvent::query()->where('license_order_id', $o->id)->orderBy('created_at')->orderBy('id')->get()->map(fn (LicenseOrderEvent $e): array => ['id' => $e->id, 'event' => $e->event, 'at' => $e->created_at?->toIso8601String(), 'actor' => $e->actor_id === null ? null : User::query()->find($e->actor_id)?->name])->all(),
            'can' => [
                'manage' => $manage, 'sign' => $manage && ! in_array($o->status, ['cancelled', 'review'], true),
                'cancel' => $manage && $o->status !== 'cancelled' && ($state === null || $state['void'] || $state['status'] === 'draft'), 'invoice' => $manage && in_array($o->status, ['pending_payment', 'review'], true) && ($state['void'] ?? false),
            ],
        ]);
    }

    public function payload(Request $request, string $order, Orders $orders): JsonResponse|RedirectResponse
    {
        $o = LicenseOrder::query()->findOrFail($order);
        try {
            $payload = $orders->payload($o);
        } catch (ValidationException $e) {
            return to_route('licensing.show', $order)->withErrors($e->errors());
        }
        $orders->downloaded($o, $this->currentUser($request));

        return response()->json($payload, 200, ['Content-Disposition' => "attachment; filename=\"{$o->number}-{$o->serial}.json\"", 'Cache-Control' => 'private, no-store'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function cancel(Request $request, string $order, Orders $orders): RedirectResponse
    {
        $orders->cancel(LicenseOrder::query()->findOrFail($order), $this->currentUser($request));

        return to_route('licensing.show', $order);
    }

    public function invoiceAgain(Request $request, string $order, Orders $orders): RedirectResponse
    {
        $orders->invoiceAgain(LicenseOrder::query()->findOrFail($order), $this->currentUser($request));

        return to_route('licensing.show', $order);
    }

    public function enter(Request $request, string $order, Orders $orders): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:16384']]);
        $orders->enter(LicenseOrder::query()->findOrFail($order), $request->string('token')->toString(), $this->currentUser($request));

        return to_route('licensing.show', $order);
    }
}

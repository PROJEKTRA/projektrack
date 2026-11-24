<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Http;

use App\Audit\AuditLog;
use App\Domain\Money\Amount;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Contracts\CompanyDirectory;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Rates\Domain\ExchangeRate;
use Modules\Rates\Domain\RateRule;
use Modules\Rates\Domain\Rates;

class RateController extends Controller
{
    /** Scopes offered in the form; the others follow the same rules. */
    private const array OFFERED = ['tenant', 'user', 'company', 'project'];

    public function index(CompanyDirectory $companies, ProjectDirectory $projects, TenantContext $tenant): Response
    {
        $rules = RateRule::query()->orderBy('scope')->orderBy('scope_id')->orderByDesc('valid_from')->get();
        $ids = fn (string $scope): array => array_values($rules->where('scope', $scope)->map(fn (RateRule $r): string => (string) $r->scope_id)->unique()->all());
        $names = [
            ...$companies->names($ids('company')),
            ...array_column($projects->projects($ids('project')), 'name', 'id'),
            ...User::query()->whereIn('id', $ids('user'))->get()->mapWithKeys(fn (User $u): array => [$u->id => $u->name])->all(),
        ];

        return Inertia::render('Rates::Rates', [
            'rules' => $rules->map(fn (RateRule $r): array => [
                'id' => $r->id, 'scope' => $r->scope, 'name' => $names[$r->scope_id ?? ''] ?? '', 'rate' => $r->rate, 'currency' => $r->currency,
                'valid_from' => $r->valid_from->toDateString(), 'valid_to' => $r->valid_to?->toDateString(),
            ])->all(),
            'exchange' => ExchangeRate::query()->orderByDesc('rate_date')->orderBy('from_currency')->limit(50)->get()->map(fn (ExchangeRate $e): array => ['id' => $e->id, 'from' => $e->from_currency, 'to' => $e->to_currency, 'rate' => $e->rate, 'date' => $e->rate_date->toDateString()])->all(),
            'scopes' => self::OFFERED,
            'choices' => [
                'tenant' => [],
                'user' => User::query()->orderBy('name')->get()->map(fn (User $u): array => ['value' => $u->id, 'text' => $u->name])->all(),
                'company' => array_map(fn (array $c): array => ['value' => $c['id'], 'text' => $c['name']], $companies->choices()),
                'project' => array_map(fn (array $p): array => ['value' => $p['id'], 'text' => $p['name']], $projects->active()),
            ],
            'currency' => $tenant->current()->base_currency,
            'today' => now($tenant->current()->timezone)->toDateString(),
        ]);
    }

    public function store(Request $request, Rates $rates, CompanyDirectory $companies, ProjectDirectory $projects): RedirectResponse
    {
        $request->validate([
            'scope' => ['required', Rule::in(self::OFFERED)],
            'scope_id' => ['nullable', 'required_unless:scope,tenant', 'ulid'],
            'rate' => ['required', 'string', 'regex:/^\d{1,10}([.,]\d{1,4})?\z/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}\z/', fn (string $attribute, mixed $value, callable $fail) => is_string($value) && Amount::isCurrency($value) ?: $fail(__('rates:errors.currency'))],
            'valid_from' => ['required', 'date_format:Y-m-d'],
        ]);
        $scope = $request->string('scope')->toString();
        $id = $scope === 'tenant' ? null : $request->string('scope_id')->lower()->toString();
        $known = match ($scope) {
            'tenant' => true,
            'user' => User::query()->whereKey($id)->exists(),
            'company' => $companies->names([(string) $id]) !== [],
            default => $projects->projects([(string) $id]) !== [],
        };
        if (! $known) {
            throw ValidationException::withMessages(['scope_id' => __('rates:errors.scope')]);
        }
        $rates->add($scope, $id, str_replace(',', '.', $request->string('rate')->toString()), $request->string('currency')->toString(), $request->string('valid_from')->toString(), $this->currentUser($request));

        return to_route('rates.index');
    }

    /** A stored exchange rate of a day. BAM and EUR are fixed by law and not editable. */
    public function exchange(Request $request, TenantContext $tenant, AuditLog $audit): RedirectResponse
    {
        $currency = ['required', 'string', 'regex:/^[A-Z]{3}\z/', fn (string $attribute, mixed $value, callable $fail) => is_string($value) && Amount::isCurrency($value) ?: $fail(__('rates:errors.currency'))];
        $request->validate([
            'from_currency' => $currency, 'to_currency' => [...$currency, 'different:from_currency'],
            'rate' => ['required', 'string', 'regex:/^\d{1,9}([.,]\d{1,10})?\z/'],
            'rate_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now($tenant->current()->timezone)->toDateString()],
        ]);
        $pair = [$request->string('from_currency')->toString(), $request->string('to_currency')->toString()];
        $rate = str_replace(',', '.', $request->string('rate')->toString());
        $date = $request->string('rate_date')->toString();
        if (array_diff($pair, ['EUR', 'BAM']) === []) {
            throw ValidationException::withMessages(['rate' => __('rates:errors.fixed')]);
        }
        if (! BigDecimal::of($rate)->isPositive()) {
            throw ValidationException::withMessages(['rate' => __('rates:errors.exchange')]);
        }
        // A typing error can be replaced; values already frozen stay as they are.
        DB::transaction(function () use ($pair, $rate, $date, $request, $audit): void {
            $old = ExchangeRate::query()->where('from_currency', $pair[0])->where('to_currency', $pair[1])->whereDate('rate_date', $date)->lockForUpdate()->first();
            ExchangeRate::query()->whereKey($old?->id)->delete();
            $row = ExchangeRate::query()->create(['from_currency' => $pair[0], 'to_currency' => $pair[1], 'rate' => $rate, 'rate_date' => $date, 'created_by' => $this->currentUser($request)->id]);
            $audit->record('rates.exchange.added', $row, ['pair' => implode('/', $pair), 'rate' => $rate, 'replaces' => $old?->rate, 'date' => $date], $this->currentUser($request));
        });

        return to_route('rates.index');
    }

    public function end(Request $request, string $rule, Rates $rates): RedirectResponse
    {
        $request->validate(['valid_to' => ['required', 'date_format:Y-m-d']]);
        $rates->end(RateRule::query()->findOrFail($rule), $request->string('valid_to')->toString(), $this->currentUser($request));

        return to_route('rates.index');
    }
}

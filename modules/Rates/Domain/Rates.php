<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Rates\Domain;

use App\Audit\AuditLog;
use App\Domain\Money\Amount;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Timesheets\Contracts\TimeApproved;

/**
 * Finds the rate of an hour and freezes the value of approved time.
 */
final class Rates
{
    public function __construct(
        private readonly ProjectDirectory $projects,
        private readonly TenantContext $tenant,
        private readonly AuditLog $audit,
        private readonly ExchangeRates $exchange,
    ) {}

    /** The most specific rule in force on the day. */
    public function resolve(string $userId, string $workPackageId, string $date, string $kind = 'sales'): ?RateRule
    {
        $package = $this->projects->workPackages([$workPackageId])[$workPackageId];
        $scopes = [
            'work_package' => $workPackageId,
            'contract' => $this->projects->contractOf($workPackageId),
            'project' => $package['project_id'],
            'company' => $package['company_id'],
            'user' => $userId,
            'job_role' => $this->jobRole($userId, $date),
        ];
        $rules = RateRule::query()->where('kind', $kind)->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->where(function (Builder $q) use ($scopes): void {
                $q->where('scope', 'tenant');
                foreach (array_filter($scopes) as $scope => $id) {
                    $q->orWhere(fn (Builder $one) => $one->where('scope', $scope)->where('scope_id', $id));
                }
            })->orderBy('valid_from')->get()->keyBy('scope');

        foreach (RateRule::SCOPES as $scope) {
            if ($rules->has($scope)) {
                return $rules->get($scope);
            }
        }

        return null;
    }

    private function jobRole(string $userId, string $date): ?string
    {
        return UserJobRole::query()->where('user_id', $userId)->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))->orderByDesc('valid_from')->first()?->job_role_id;
    }

    /** Listener of the approval: no rate means no value, shown as a warning later. */
    public function value(TimeApproved $event): void
    {
        $this->valueAs('sales', $event, $event->billableMinutes);
        // Every worked minute costs, billable or not.
        $this->valueAs('cost', $event, $event->minutes);
    }

    private function valueAs(string $kind, TimeApproved $event, int $minutes): void
    {
        if (Valuation::query()->where('kind', $kind)->where('time_entry_id', $event->entryId)->exists()) {
            return;
        }
        $original = $event->corrects === null ? null : Valuation::query()->where('kind', $kind)->where('time_entry_id', $event->corrects)->first();
        if ($event->corrects !== null && $original === null) {
            return;
        }
        $rule = $original === null ? $this->resolve($event->userId, $event->workPackageId, $event->date, $kind) : null;
        if ($original === null && $rule === null) {
            return;
        }
        // A correction is valued like the entry it corrects, whatever the rates say today.
        $rate = $original->rate ?? $rule->rate ?? '0';
        $currency = $original->currency ?? $rule->currency ?? '';
        $fx = null;
        if ($rule !== null) {
            // In the currency of the project, so it can be billed; without a rate of the day it waits.
            $target = $this->projects->projects([$event->projectId])[$event->projectId]['currency'] ?? '';
            $factor = $this->exchange->on($rule->currency, $target, $event->date);
            if ($factor === null) {
                return;
            }
            if ($target !== $rule->currency) {
                $fx = (string) $factor->toScale(10, RoundingMode::HalfUp);
                $rate = (string) BigRational::of($rule->rate)->multipliedBy($factor)->toScale(4, RoundingMode::HalfUp);
            }
            $currency = $target;
        }

        Valuation::query()->create([
            'kind' => $kind,
            'time_entry_id' => $event->entryId,
            'rate_rule_id' => $original->rate_rule_id ?? $rule?->id,
            'source' => $original->source ?? $rule->scope ?? 'tenant',
            'rate' => $rate,
            'currency' => $currency,
            'minutes_valued' => $minutes,
            'amount_minor' => Amount::time($minutes, $rate, $currency),
            'valued_at' => now(),
            'rule_rate' => $fx === null ? null : $rule?->rate,
            'rule_currency' => $fx === null ? null : $rule?->currency,
            'fx_rate' => $fx,
        ]);
    }

    /**
     * Value approved time that got no value at its approval, by its work date.
     * Values that exist are never touched.
     *
     * @param  list<TimeApproved>  $entries  originals before their corrections
     */
    public function valueMissing(array $entries, User $actor): int
    {
        return DB::transaction(function () use ($entries, $actor): int {
            $ids = array_map(fn (TimeApproved $e): string => $e->entryId, $entries);
            $valued = fn (): int => Valuation::query()->whereIn('time_entry_id', $ids)->count();
            $before = $valued();
            foreach ($entries as $entry) {
                $this->value($entry);
            }
            $count = $valued() - $before;
            if ($count > 0) {
                $this->audit->record('rates.valued_later', null, ['entries' => $count], $actor);
            }

            return $count;
        }, 3);
    }

    /**
     * Add a rate from a day on. The rule in force before ends the day before;
     * a rule that starts on the same day or later is not overwritten.
     */
    public function add(string $scope, ?string $scopeId, string $rate, string $currency, string $validFrom, User $actor, string $kind = 'sales'): RateRule
    {
        return DB::transaction(function () use ($scope, $scopeId, $rate, $currency, $validFrom, $actor, $kind): RateRule {
            Tenant::query()->whereKey($this->tenant->id())->lockForUpdate()->first();
            $same = fn (): Builder => RateRule::query()->where('kind', $kind)->where('scope', $scope)->where('scope_id', $scopeId);
            if ($same()->whereDate('valid_from', '>=', $validFrom)->exists()) {
                throw ValidationException::withMessages(['valid_from' => __('rates:errors.later_rule')]);
            }
            $same()->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $validFrom))
                ->update(['valid_to' => CarbonImmutable::parse($validFrom, 'UTC')->subDay()->toDateString()]);
            $rule = RateRule::query()->create(['kind' => $kind, 'scope' => $scope, 'scope_id' => $scopeId, 'rate' => $rate, 'currency' => $currency, 'valid_from' => $validFrom]);
            $this->audit->record('rates.rule.added', $rule, ['scope' => $scope, 'valid_from' => $validFrom], $actor);

            return $rule;
        }, 3);
    }

    /** End a rule; the past stays as it was. */
    public function end(RateRule $rule, string $lastDay, User $actor): void
    {
        if ($lastDay < $rule->valid_from->toDateString()) {
            throw ValidationException::withMessages(['valid_to' => __('rates:errors.end_before_start')]);
        }
        DB::transaction(function () use ($rule, $lastDay, $actor): void {
            Tenant::query()->whereKey($this->tenant->id())->lockForUpdate()->first();
            $next = RateRule::query()->where('kind', $rule->kind)->where('scope', $rule->scope)->where('scope_id', $rule->scope_id)->whereDate('valid_from', '>', $rule->valid_from->toDateString())->whereDate('valid_from', '<=', $lastDay)->exists();
            if ($next) {
                throw ValidationException::withMessages(['valid_to' => __('rates:errors.later_rule')]);
            }
            $rule->update(['valid_to' => $lastDay]);
            $this->audit->record('rates.rule.ended', $rule, ['valid_to' => $lastDay], $actor);
        });
    }
}

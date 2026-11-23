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
    ) {}

    /** The most specific rule in force on the day. */
    public function resolve(string $userId, string $workPackageId, string $date): ?RateRule
    {
        $package = $this->projects->workPackages([$workPackageId])[$workPackageId];
        $scopes = [
            'work_package' => $workPackageId,
            'contract' => $this->projects->contractOf($workPackageId),
            'project' => $package['project_id'],
            'company' => $package['company_id'],
            'user' => $userId,
        ];
        $rules = RateRule::query()->whereDate('valid_from', '<=', $date)
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

    /** Listener of the approval: no rate means no value, shown as a warning later. */
    public function value(TimeApproved $event): void
    {
        $original = $event->corrects === null ? null : Valuation::query()->where('time_entry_id', $event->corrects)->first();
        if ($event->corrects !== null && $original === null) {
            return;
        }
        $rule = $original === null ? $this->resolve($event->userId, $event->workPackageId, $event->date) : null;
        if ($original === null && $rule === null) {
            return;
        }
        // A correction is valued like the entry it corrects, whatever the rates say today.
        // Never frozen in a currency the project cannot be billed in.
        if ($rule !== null && $rule->currency !== ($this->projects->projects([$event->projectId])[$event->projectId]['currency'] ?? null)) {
            return;
        }
        $rate = $original->rate ?? $rule->rate ?? '0';
        $currency = $original->currency ?? $rule->currency ?? '';

        Valuation::query()->create([
            'time_entry_id' => $event->entryId,
            'rate_rule_id' => $original->rate_rule_id ?? $rule?->id,
            'source' => $original->source ?? $rule->scope ?? 'tenant',
            'rate' => $rate,
            'currency' => $currency,
            'minutes_valued' => $event->billableMinutes,
            'amount_minor' => Amount::time($event->billableMinutes, $rate, $currency),
            'valued_at' => now(),
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
            $before = Valuation::query()->whereIn('time_entry_id', array_map(fn (TimeApproved $e): string => $e->entryId, $entries))->pluck('time_entry_id')->all();
            $missing = array_filter($entries, fn (TimeApproved $e): bool => ! in_array($e->entryId, $before, true));
            foreach ($missing as $entry) {
                $this->value($entry);
            }
            $count = Valuation::query()->whereIn('time_entry_id', array_map(fn (TimeApproved $e): string => $e->entryId, $missing))->count();
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
    public function add(string $scope, ?string $scopeId, string $rate, string $currency, string $validFrom, User $actor): RateRule
    {
        return DB::transaction(function () use ($scope, $scopeId, $rate, $currency, $validFrom, $actor): RateRule {
            Tenant::query()->whereKey($this->tenant->id())->lockForUpdate()->first();
            $same = fn (): Builder => RateRule::query()->where('scope', $scope)->where('scope_id', $scopeId);
            if ($same()->whereDate('valid_from', '>=', $validFrom)->exists()) {
                throw ValidationException::withMessages(['valid_from' => __('rates:errors.later_rule')]);
            }
            $same()->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $validFrom))
                ->update(['valid_to' => CarbonImmutable::parse($validFrom, 'UTC')->subDay()->toDateString()]);
            $rule = RateRule::query()->create(['scope' => $scope, 'scope_id' => $scopeId, 'rate' => $rate, 'currency' => $currency, 'valid_from' => $validFrom]);
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
            $next = RateRule::query()->where('scope', $rule->scope)->where('scope_id', $rule->scope_id)->whereDate('valid_from', '>', $rule->valid_from->toDateString())->whereDate('valid_from', '<=', $lastDay)->exists();
            if ($next) {
                throw ValidationException::withMessages(['valid_to' => __('rates:errors.later_rule')]);
            }
            $rule->update(['valid_to' => $lastDay]);
            $this->audit->record('rates.rule.ended', $rule, ['valid_to' => $lastDay], $actor);
        });
    }
}

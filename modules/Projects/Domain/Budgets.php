<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Domain;

use App\Authorization\Permissions;
use App\Models\ProjectRole;
use App\Models\User;
use App\Support\Lists;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Contracts\BudgetWatch;

final class Budgets implements BudgetWatch
{
    private const array THRESHOLDS = [70, 90, 100];

    public function __construct(private readonly Permissions $permissions) {}

    public function packagesOf(string $contractId): array
    {
        return Lists::strings(WorkPackage::query()->where('contract_id', $contractId)->pluck('id'));
    }

    public function approved(string $contractId, int $minutes): void
    {
        $contract = Contract::query()->whereKey($contractId)->lockForUpdate()->first();
        if ($contract === null || $contract->budget_minutes === null || $contract->budget_minutes === 0) {
            return;
        }
        $told = ContractBudgetAlert::query()->where('contract_id', $contract->id)->pluck('threshold_pct')->all();
        $due = array_filter(self::THRESHOLDS, fn (int $t): bool => ! in_array($t, $told, true) && $minutes * 100 >= $contract->budget_minutes * $t);
        if ($due === []) {
            return;
        }
        foreach ($due as $threshold) {
            ContractBudgetAlert::query()->create(['contract_id' => $contract->id, 'threshold_pct' => $threshold]);
        }
        // Several thresholds at once are one notice, with the highest.
        $notice = new BudgetReached($contract->number, max($due), $contract->project_id);
        DB::afterCommit(fn () => $this->recipients($contract)->each(fn (User $u) => $u->notify($notice)));
    }

    /** @return Collection<int, User> project leads of the project and everyone who manages contracts */
    private function recipients(Contract $contract): Collection
    {
        $lead = ProjectRole::query()->where('key', 'project_lead')->first();
        $leads = Lists::strings(ProjectMember::query()->where('project_id', $contract->project_id)->where('project_role_id', $lead?->id)->pluck('user_id'));

        return User::query()->where('user_type', '!=', 'portal')->get()
            ->filter(fn (User $u): bool => in_array($u->id, $leads, true) || $this->permissions->allows($u, 'projects.contracts.manage'))->values();
    }
}

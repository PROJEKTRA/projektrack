<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use App\Audit\AuditLog;
use App\Authorization\Permissions;
use App\Models\User;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Projects\Contracts\BudgetWatch;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Timesheets\Contracts\TimeApproved;

/**
 * Booking, submitting, approving and correcting time.
 */
final class Timesheets
{
    public function __construct(
        private readonly ProjectDirectory $projects,
        private readonly Permissions $permissions,
        private readonly Settings $settings,
        private readonly AuditLog $audit,
        private readonly BudgetWatch $budgets,
    ) {}

    /**
     * Set the time of one cell of the timesheet: a user, a work package, a day.
     * Zero minutes empty it. What is submitted or approved stays untouched.
     */
    public function book(User $user, string $workPackageId, string $date, int $minutes, ?string $description): ?TimeEntry
    {
        return DB::transaction(function () use ($user, $workPackageId, $date, $minutes, $description): ?TimeEntry {
            // Two requests for one user wait for each other.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->ensureOpen($date);

            $entry = TimeEntry::query()->where('user_id', $user->id)->where('work_package_id', $workPackageId)->whereDate('work_date', $date)
                ->whereIn('status', TimeEntry::EDITABLE)->whereNull('corrects_id')->lockForUpdate()->first();

            if ($minutes === 0) {
                $entry?->delete();

                return null;
            }
            if (! $this->projects->mayBook($user->id, $workPackageId, $date)) {
                throw ValidationException::withMessages(['work_package_id' => __('timesheets:errors.not_bookable')]);
            }
            $day = TimeEntry::query()->where('user_id', $user->id)->whereDate('work_date', $date)->when($entry !== null, fn (Builder $q) => $q->whereKeyNot($entry?->id))->sum('duration_minutes');
            if ((int) $day + $minutes > 1440) {
                throw ValidationException::withMessages(['minutes' => __('timesheets:errors.day_full')]);
            }
            $from = $this->settings->get('timesheets.description_from_minutes');
            if (is_int($from) && $from > 0 && $minutes >= $from && trim((string) $description) === '') {
                throw ValidationException::withMessages(['description' => __('timesheets:errors.description_required', ['minutes' => $from])]);
            }

            $package = $this->projects->workPackages([$workPackageId])[$workPackageId];
            $values = [
                'duration_minutes' => $minutes,
                'billable_minutes' => $this->billable($workPackageId, $package['billable'], $minutes),
                'description' => $description === null || trim($description) === '' ? null : trim($description),
                'status' => 'draft',
                'reject_reason' => null,
            ];
            if ($entry === null) {
                return TimeEntry::query()->create([...$values, 'user_id' => $user->id, 'project_id' => $package['project_id'], 'work_package_id' => $workPackageId, 'work_date' => $date]);
            }
            $entry->update($values);

            return $entry;
        }, 3);
    }

    /** Add to the draft of a cell, for example what a timer measured. */
    public function addTo(User $user, string $workPackageId, string $date, int $minutes, ?string $description): void
    {
        DB::transaction(function () use ($user, $workPackageId, $date, $minutes, $description): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $entry = TimeEntry::query()->where('user_id', $user->id)->where('work_package_id', $workPackageId)->whereDate('work_date', $date)
                ->whereIn('status', TimeEntry::EDITABLE)->whereNull('corrects_id')->first();
            $notes = array_filter([$entry?->description, $description], fn (?string $n): bool => $n !== null && trim($n) !== '');
            $this->book($user, $workPackageId, $date, ($entry->duration_minutes ?? 0) + $minutes, $notes === [] ? null : mb_substr(implode('; ', array_unique($notes)), 0, 2000));
        }, 3);
    }

    /** Submit the drafts and rejected entries of the week that holds the date. */
    public function submitWeek(User $user, string $date): int
    {
        [$from, $to] = self::week($date);

        return DB::transaction(function () use ($user, $from, $to): int {
            $entries = TimeEntry::query()->where('user_id', $user->id)->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->whereIn('status', TimeEntry::EDITABLE)->lockForUpdate()->get();
            foreach ($entries as $entry) {
                $this->ensureOpen($entry->work_date->toDateString());
                $entry->update(['status' => 'submitted', 'submitted_at' => now(), 'reject_reason' => null]);
            }

            return $entries->count();
        }, 3);
    }

    /** @return Builder<TimeEntry> submitted entries of others the user may decide on */
    public function pendingFor(User $approver): Builder
    {
        $query = TimeEntry::query()->where('status', 'submitted')->where('user_id', '!=', $approver->id);
        if ($this->permissions->allows($approver, 'timesheets.approve')) {
            return $query;
        }
        $ids = TimeEntry::query()->where('status', 'submitted')->distinct()->get(['project_id'])->map(fn (TimeEntry $e): string => $e->project_id)
            ->filter(fn (string $id): bool => $this->permissions->allows($approver, 'timesheets.approve', $id));

        return $query->whereIn('project_id', $ids->values());
    }

    /** @param list<string> $ids */
    public function approve(User $approver, array $ids): int
    {
        return DB::transaction(function () use ($approver, $ids): int {
            $entries = $this->pendingFor($approver)->whereIn('id', $ids)->lockForUpdate()->get();
            foreach ($entries as $entry) {
                $this->ensureOpen($entry->work_date->toDateString());
                // Billable time follows the rules at approval: rounding or billable flag may have changed.
                $package = $this->projects->workPackages([$entry->work_package_id])[$entry->work_package_id];
                $entry->update([
                    'status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now(),
                    'billable_minutes' => $this->billable($entry->work_package_id, $package['billable'], $entry->duration_minutes),
                ]);
                $this->audit->record('timesheets.entry.approved', $entry, ['minutes' => $entry->duration_minutes], $approver);
                $this->announce($entry);
            }

            return $entries->count();
        }, 3);
    }

    /** @param list<string> $ids */
    public function reject(User $approver, array $ids, string $reason): int
    {
        $entries = DB::transaction(function () use ($approver, $ids, $reason): array {
            $entries = $this->pendingFor($approver)->whereIn('id', $ids)->lockForUpdate()->get()->all();
            foreach ($entries as $entry) {
                $entry->update(['status' => 'rejected', 'reject_reason' => $reason]);
                $this->audit->record('timesheets.entry.rejected', $entry, [], $approver);
            }

            return $entries;
        }, 3);

        // One notice per person, not one per entry.
        foreach (array_unique(array_map(fn (TimeEntry $e): string => $e->user_id, $entries)) as $userId) {
            $first = array_find($entries, fn (TimeEntry $e): bool => $e->user_id === $userId);
            User::query()->findOrFail($userId)->notify(new TimeRejected($reason, $first?->work_date->toDateString() ?? ''));
        }

        return count($entries);
    }

    /** Book the difference to the right value; the approved entry itself stays. */
    public function correct(User $actor, TimeEntry $original, int $minutes, string $reason): TimeEntry
    {
        return DB::transaction(function () use ($actor, $original, $minutes, $reason): TimeEntry {
            $entry = TimeEntry::query()->whereKey($original->id)->lockForUpdate()->firstOrFail();
            abort_if($entry->user_id === $actor->id, 403);
            if ($entry->status !== 'approved' || $entry->corrects_id !== null) {
                throw ValidationException::withMessages(['minutes' => __('timesheets:errors.not_correctable')]);
            }
            $this->ensureOpen($entry->work_date->toDateString());

            $current = $entry->duration_minutes;
            $billed = $entry->billable_minutes;
            foreach (TimeEntry::query()->where('corrects_id', $entry->id)->get() as $correction) {
                $current += $correction->duration_minutes;
                $billed += $correction->billable_minutes;
            }
            if ($minutes === $current) {
                throw ValidationException::withMessages(['minutes' => __('timesheets:errors.no_difference')]);
            }
            $day = TimeEntry::query()->where('user_id', $entry->user_id)->whereDate('work_date', $entry->work_date->toDateString())->sum('duration_minutes');
            if ((int) $day + $minutes - $current > 1440) {
                throw ValidationException::withMessages(['minutes' => __('timesheets:errors.day_full')]);
            }
            $package = $this->projects->workPackages([$entry->work_package_id])[$entry->work_package_id];

            $correction = TimeEntry::query()->create([
                'user_id' => $entry->user_id, 'project_id' => $entry->project_id, 'work_package_id' => $entry->work_package_id, 'work_date' => $entry->work_date->toDateString(),
                'duration_minutes' => $minutes - $current,
                'billable_minutes' => $this->billable($entry->work_package_id, $entry->billable_minutes !== 0 || $package['billable'], $minutes) - $billed,
                'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'corrects_id' => $entry->id, 'correction_reason' => $reason,
            ]);
            $this->audit->record('timesheets.entry.corrected', $correction, ['entry' => $entry->id, 'minutes' => $correction->duration_minutes], $actor);

            $this->announce($correction);

            return $correction;
        }, 3);
    }

    public function setLocked(User $actor, string $month, bool $locked, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $month, $locked, $reason): void {
            $period = TimePeriod::query()->updateOrCreate(['month' => $month], ['locked' => $locked]);
            $this->audit->record($locked ? 'timesheets.period.locked' : 'timesheets.period.reopened', $period, ['month' => $month, ...($reason === null ? [] : ['reason' => $reason])], $actor);
        });
    }

    /** @return array{0: string, 1: string} Monday and Sunday of the week */
    public static function week(string $date): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC') ?? CarbonImmutable::now('UTC');
        $monday = $day->subDays($day->dayOfWeekIso - 1);

        return [$monday->toDateString(), $monday->addDays(6)->toDateString()];
    }

    private function billable(string $workPackageId, bool $billable, int $minutes): int
    {
        $rule = $this->projects->rounding($workPackageId);

        return $billable ? Rounding::billable($minutes, $rule['rounding'], $rule['minimum']) : 0;
    }

    private function announce(TimeEntry $entry): void
    {
        $contract = $this->projects->contractOf($entry->work_package_id);
        if ($contract !== null) {
            // Read with a lock, so a parallel approval is counted.
            $this->budgets->approved($contract, fn (): int => TimeEntry::query()->where('status', 'approved')->whereIn('work_package_id', $this->budgets->packagesOf($contract))->lockForUpdate()->get(['id', 'tenant_id', 'duration_minutes'])->sum(fn (TimeEntry $e): int => $e->duration_minutes));
        }
        event(new TimeApproved($entry->id, $entry->user_id, $entry->work_package_id, $entry->project_id, $entry->work_date->toDateString(), $entry->billable_minutes, $entry->duration_minutes, $entry->corrects_id));
    }

    private function ensureOpen(string $date): void
    {
        if (TimePeriod::isLocked($date)) {
            throw ValidationException::withMessages(['date' => __('timesheets:errors.locked', ['month' => substr($date, 0, 7)])]);
        }
    }
}

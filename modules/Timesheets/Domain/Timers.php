<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Timesheets\Domain;

use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Projects\Contracts\ProjectDirectory;

/**
 * Starts and stops the one timer of a user. Stopping adds the time to the
 * draft of the day; over midnight each day gets its part.
 */
final class Timers
{
    public function __construct(
        private readonly Timesheets $timesheets,
        private readonly ProjectDirectory $projects,
        private readonly TenantContext $tenant,
    ) {}

    public function running(User $user): ?Timer
    {
        return Timer::query()->where('user_id', $user->id)->first();
    }

    public function start(User $user, string $workPackageId): Timer
    {
        return DB::transaction(function () use ($user, $workPackageId): Timer {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $this->projects->mayBook($user->id, $workPackageId, now($this->zone($user))->toDateString())) {
                throw ValidationException::withMessages(['work_package_id' => __('timesheets:errors.not_bookable')]);
            }
            $this->stop($user, null);

            return Timer::query()->create(['user_id' => $user->id, 'work_package_id' => $workPackageId, 'started_at' => now()]);
        }, 3);
    }

    /** Stop and book. What cannot be booked keeps the timer running, so nothing is lost. */
    public function stop(User $user, ?string $description): int
    {
        return DB::transaction(function () use ($user, $description): int {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $timer = Timer::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($timer === null) {
                return 0;
            }
            $total = 0;
            foreach (self::split(CarbonImmutable::instance($timer->started_at), CarbonImmutable::now(), $this->zone($user)) as $date => $minutes) {
                $this->timesheets->addTo($user, $timer->work_package_id, $date, $minutes, $description);
                $total += $minutes;
            }
            $timer->delete();

            return $total;
        }, 3);
    }

    /** Throw the timer away without booking. */
    public function discard(User $user): void
    {
        Timer::query()->where('user_id', $user->id)->get()->each->delete();
    }

    /**
     * Real elapsed minutes per local day, also across a clock change.
     *
     * @return array<string, int> date => minutes, without empty days
     */
    public static function split(CarbonImmutable $from, CarbonImmutable $to, string $zone): array
    {
        $parts = [];
        $at = $from->setTimezone($zone);
        $end = $to->setTimezone($zone);
        while ($at < $end) {
            $midnight = $at->addDay()->startOfDay();
            $until = $midnight < $end ? $midnight : $end;
            // Whole minutes, the last begun one counts down: never more than was worked.
            $minutes = intdiv($until->getTimestamp() - $at->getTimestamp(), 60);
            if ($minutes > 0) {
                $parts[$at->toDateString()] = $minutes;
            }
            $at = $until;
        }

        return $parts;
    }

    private function zone(User $user): string
    {
        return $user->timezone ?? $this->tenant->current()->timezone;
    }
}

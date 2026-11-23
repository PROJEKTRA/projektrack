<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The license of this installation (S-0974). Validated offline, with the keys
 * of the release, each time it is read: a changed row in the database counts
 * as no license. A refused token never replaces the installed one.
 */
final class InstanceLicense
{
    /** Clock this far behind the latest time ever seen: a warning, no lockout. */
    private const int CLOCK_TOLERANCE_HOURS = 48;

    private bool $loaded = false;

    private ?License $license = null;

    public function __construct(private readonly LicenseKeys $keys, private readonly InstanceId $instance) {}

    /** @throws InvalidLicense */
    public function install(string $token): License
    {
        $token = trim($token);
        $license = LicenseToken::decode($token, $this->keys);

        if ($license->instanceId !== $this->instance->get()) {
            throw new InvalidLicense('The license belongs to another installation.');
        }
        if ($license->status(CarbonImmutable::now('UTC')) === LicenseStatus::ReadOnly) {
            throw new InvalidLicense('The license has already ended.');
        }

        DB::transaction(function () use ($license, $token): void {
            $row = DB::table('instance_licenses')->where('license_id', $license->id)->lockForUpdate()->first(['serial', 'token']);

            // Never back to an earlier, perhaps larger, issue of the same license.
            $serial = $row !== null && is_numeric($row->serial) ? (int) $row->serial : 0;
            if ($license->serial < $serial || ($license->serial === $serial && $row?->token !== $token)) {
                throw new InvalidLicense('A newer issue of this license is already installed.');
            }

            DB::table('instance_licenses')->update(['is_active' => false]);
            DB::table('instance_licenses')->updateOrInsert(['license_id' => $license->id], [
                'serial' => $license->serial,
                'token' => $token,
                'is_active' => true,
                'installed_at' => now(),
            ]);
        });

        $this->loaded = false;

        return $license;
    }

    /** The installed license, or null for Community. */
    public function current(): ?License
    {
        if ($this->loaded) {
            return $this->license;
        }

        $this->license = null;
        $row = DB::table('instance_licenses')->where('is_active', true)->first(['license_id', 'serial', 'token']);

        if ($row !== null && is_string($row->token)) {
            try {
                $license = LicenseToken::decode($row->token, $this->keys);

                // The row may only say what the signed token says.
                if ($license->id !== $row->license_id || (string) $license->serial !== (is_scalar($row->serial) ? (string) $row->serial : '')
                    || $license->instanceId !== $this->instance->get()) {
                    throw new InvalidLicense('The stored license does not match its token or this installation.');
                }
                $this->license = $license;
            } catch (InvalidLicense $e) {
                Log::critical('The installed license is not valid', ['reason' => $e->getMessage()]);
            }
        }
        $this->loaded = true;

        return $this->license;
    }

    public function status(): ?LicenseStatus
    {
        return $this->current()?->status(CarbonImmutable::now('UTC'));
    }

    /** Remembers the latest time seen; true when the clock is far behind it. */
    public function clockProblem(): bool
    {
        $this->instance->get();
        $now = CarbonImmutable::now('UTC');
        DB::table('instance_state')->where('id', 1)
            ->where(fn (Builder $query) => $query->whereNull('latest_time')->orWhere('latest_time', '<', $now))
            ->update(['latest_time' => $now]);
        $latest = DB::table('instance_state')->where('id', 1)->value('latest_time');

        return is_string($latest) && $now->addHours(self::CLOCK_TOLERANCE_HOURS)->lessThan(CarbonImmutable::parse($latest, 'UTC'));
    }
}

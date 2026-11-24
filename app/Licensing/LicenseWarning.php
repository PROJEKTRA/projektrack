<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use App\Authorization\UserType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What operator administrators are told about the license; nothing is locked by it.
 */
final class LicenseWarning
{
    /** Days before the end at which the renewal is mentioned. */
    private const int RENEWAL_DAYS = 60;

    public function __construct(private readonly InstanceLicense $licenses) {}

    /** @return array{message: string, args: array<string, scalar>}|null */
    public function current(): ?array
    {
        $license = $this->licenses->current();
        $status = $this->licenses->status();
        if ($status === LicenseStatus::Invalid) {
            return ['message' => 'core:license.warning.invalid', 'args' => []];
        }
        if ($license === null || $status === null) {
            return null;
        }
        $days = (int) floor(CarbonImmutable::now('UTC')->diffInDays($license->expiresAt, false));
        $message = match ($status) {
            LicenseStatus::ReadOnly => 'core:license.warning.read_only',
            LicenseStatus::Grace => 'core:license.warning.grace',
            LicenseStatus::PendingPayment => 'core:license.warning.pending',
            LicenseStatus::NotYetValid => 'core:license.warning.not_yet',
            default => null,
        };
        if ($message !== null) {
            return ['message' => $message, 'args' => []];
        }
        foreach ([[UserType::Full, $license->fullSeats], [UserType::Light, $license->lightSeats]] as [$type, $seats]) {
            $used = DB::table('users')->where('user_type', $type->value)->count();
            if ($used > $seats) {
                return ['message' => 'core:license.warning.seats', 'args' => ['used' => $used, 'seats' => $seats]];
            }
        }

        return $days <= self::RENEWAL_DAYS ? ['message' => 'core:license.warning.renewal', 'args' => ['days' => max(0, $days)]] : null;
    }
}

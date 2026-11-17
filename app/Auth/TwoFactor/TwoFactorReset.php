<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

/**
 * An admin removed a user's two-factor setup. The audit log (S-0907) records it.
 */
final readonly class TwoFactorReset
{
    public function __construct(
        public string $tenantId,
        public string $userId,
        public string $actorId,
    ) {}
}

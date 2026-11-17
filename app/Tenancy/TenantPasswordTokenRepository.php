<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Tenancy;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Database\Query\Builder;
use SensitiveParameter;

/**
 * Password reset tokens are stored per tenant. The same e-mail address can
 * exist in several tenants; a token issued in one tenant must never reset
 * the account of another.
 */
final class TenantPasswordTokenRepository extends DatabaseTokenRepository
{
    /**
     * @return array<string, mixed>
     */
    protected function getPayload($email, #[SensitiveParameter] $token): array
    {
        /** @var array<string, mixed> $payload */
        $payload = parent::getPayload($email, $token);

        return ['tenant_id' => app(TenantContext::class)->id()] + $payload;
    }

    protected function getTable(): Builder
    {
        return parent::getTable()->where('tenant_id', app(TenantContext::class)->id());
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Documents;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gapless numbers (ADR-0004 section 10): the number is taken in the same
 * transaction that issues the document, so a failure takes it back.
 */
final class Numbers
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** The next number, for example "INV-2026-0001", in the year of the tenant's today. */
    public function next(string $documentType, string $prefix): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A number is only taken inside the transaction that uses it.');
        }
        // All numbering of a tenant waits here, also before its first row exists.
        Tenant::query()->whereKey($this->tenant->id())->lockForUpdate()->first();

        $year = now($this->tenant->current()->timezone)->format('Y');
        $sequence = NumberSequence::query()->where('document_type', $documentType)->where('period_key', $year)->lockForUpdate()->first()
            ?? NumberSequence::query()->create(['document_type' => $documentType, 'period_key' => $year, 'next_value' => 1]);
        $value = $sequence->next_value;
        $sequence->update(['next_value' => $value + 1]);

        return sprintf('%s-%s-%04d', $prefix, $year, $value);
    }
}

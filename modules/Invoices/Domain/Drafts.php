<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\Models\User;
use Modules\Invoices\Contracts\InvoiceDrafts;

final class Drafts implements InvoiceDrafts
{
    public function __construct(private readonly Invoices $invoices) {}

    public function draft(string $companyId, string $from, string $to, array $lines, User $actor): string
    {
        return $this->invoices->draft($companyId, $from, $to, $lines, $actor)->id;
    }

    public function problems(string $invoiceId): array
    {
        $result = [];
        foreach ($this->invoices->preview(Invoice::query()->findOrFail($invoiceId))['problems'] as $problem) {
            $result[$problem] = __("invoices:problem.{$problem}");
        }

        return $result;
    }

    public function discard(string $invoiceId, User $actor): void
    {
        $this->invoices->discard(Invoice::query()->findOrFail($invoiceId), $actor);
    }

    public function state(string $invoiceId): ?array
    {
        $i = Invoice::query()->find($invoiceId);

        return $i === null ? null : [
            'status' => $i->status, 'net_minor' => $i->net_minor, 'currency' => $i->currency, 'due_date' => $i->due_date?->toDateString(),
            'void' => $i->status === 'cancelled' || ($i->status === 'finalized' && $i->gross_minor + $i->credited_minor === 0 && $i->paid_minor === 0),
            'settled' => $i->status === 'finalized' && $i->credited_minor === 0 && $i->paid_minor > 0 && $i->openMinor() <= 0,
        ];
    }
}

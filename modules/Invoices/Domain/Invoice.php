<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Invoices\Domain;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * An invoice or the cancellation of one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $kind
 * @property string $status
 * @property string|null $number
 * @property string $company_id
 * @property string|null $project_id
 * @property string|null $cancels_id
 * @property CarbonInterface|null $issue_date
 * @property CarbonInterface $service_from
 * @property CarbonInterface $service_to
 * @property CarbonInterface|null $due_date
 * @property string $currency
 * @property string $locale
 * @property int $net_minor
 * @property int $tax_minor
 * @property int $gross_minor
 * @property int $paid_minor
 * @property string|null $snapshot
 * @property string|null $content_hash
 * @property CarbonInterface|null $finalized_at
 * @property string|null $finalized_by
 * @property string|null $fiscal_reference
 * @property string|null $override_case
 * @property string|null $override_reason
 * @property string|null $override_by
 * @property string|null $credits_id
 * @property int $credited_minor
 */
#[Fillable(['kind', 'status', 'number', 'company_id', 'project_id', 'cancels_id', 'issue_date', 'service_from', 'service_to', 'due_date', 'currency', 'locale', 'net_minor', 'tax_minor', 'gross_minor', 'paid_minor', 'snapshot', 'content_hash', 'finalized_at', 'finalized_by', 'fiscal_reference', 'override_case', 'override_reason', 'override_by', 'credits_id', 'credited_minor'])]
class Invoice extends Model
{
    use BelongsToTenant, HasUlids;

    /** What may still change on a finalized invoice: payments, the cancelled state, the fiscal reference. */
    private const array AFTER_FINALIZATION = ['status', 'paid_minor', 'credited_minor', 'fiscal_reference', 'updated_at'];

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /** What is still to be paid: credit notes reduce it like payments do. */
    public function openMinor(): int
    {
        return $this->kind === 'invoice' && $this->status === 'finalized' ? $this->gross_minor - $this->paid_minor + $this->credited_minor : 0;
    }

    /** @return array<mixed> the document as issued, checked against its hash */
    public function content(): array
    {
        if ($this->snapshot === null || $this->content_hash === null || ! hash_equals($this->content_hash, hash('sha256', $this->snapshot))) {
            throw new LogicException("The content of {$this->number} was changed.");
        }
        $content = json_decode($this->snapshot, true, flags: JSON_THROW_ON_ERROR);

        return is_array($content) ? $content : [];
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if ($invoice->getOriginal('status') !== 'draft' && array_diff(array_keys($invoice->getDirty()), self::AFTER_FINALIZATION) !== []) {
                throw new LogicException('Finalized invoices do not change.');
            }
            if ($invoice->getOriginal('status') !== 'draft' && $invoice->status === 'draft') {
                throw new LogicException('A finalized invoice never becomes a draft again.');
            }
        });
        static::deleting(function (Invoice $invoice): void {
            if ($invoice->getOriginal('status') !== 'draft') {
                throw new LogicException('Finalized invoices are not deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'service_from' => 'date', 'service_to' => 'date', 'due_date' => 'date', 'net_minor' => 'integer', 'tax_minor' => 'integer', 'gross_minor' => 'integer', 'paid_minor' => 'integer', 'credited_minor' => 'integer', 'finalized_at' => 'datetime'];
    }
}

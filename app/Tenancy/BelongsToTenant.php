<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned (data model section 1.1).
 *
 * - every query is limited to the current tenant (TenantScope);
 * - new rows get tenant_id from the context, and a different tenant_id is refused;
 * - tenant_id never changes after creation.
 *
 * @mixin Model
 *
 * @property string $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $current = app(TenantContext::class)->id();
            $given = $model->getAttribute('tenant_id');

            if ($given === null) {
                $model->setAttribute('tenant_id', $current);
            } elseif ($given !== $current) {
                throw new CrossTenantWrite(sprintf('Refusing to create %s for another tenant.', $model::class));
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new CrossTenantWrite(sprintf('The tenant of %s cannot change.', $model::class));
            }

            if ($model->getAttribute('tenant_id') !== app(TenantContext::class)->id()) {
                throw new CrossTenantWrite(sprintf('Refusing to update %s of another tenant.', $model::class));
            }
        });

        static::deleting(function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== app(TenantContext::class)->id()) {
                throw new CrossTenantWrite(sprintf('Refusing to delete %s of another tenant.', $model::class));
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

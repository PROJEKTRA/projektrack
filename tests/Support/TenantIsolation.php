<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Tests\Support;

use App\Models\Tenant;
use App\Tenancy\CrossTenantWrite;
use App\Tenancy\MissingTenantContext;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared isolation suite for tenant-owned models (S-0902 notes: part of the
 * Definition of Done of every story that adds a model).
 *
 * Usage in a Pest file:
 *
 *     Tests\Support\TenantIsolation::for(Project::class, fn () => Project::factory()->create());
 */
final class TenantIsolation
{
    /**
     * @param  class-string<Model>  $model
     * @param  Closure(): Model  $make  creates one row in the current tenant
     */
    public static function for(string $model, Closure $make): void
    {
        $short = class_basename($model);

        test("{$short}: lists only rows of the current tenant", function () use ($model, $make) {
            $own = $make();
            $other = TenantIsolation::inOtherTenant($make);

            $ids = $model::query()->pluck('id')->all();

            expect($ids)->toContain($own->getKey())->not->toContain($other->getKey());
        });

        test("{$short}: a known id of another tenant is not found", function () use ($model, $make) {
            $other = TenantIsolation::inOtherTenant($make);

            expect($model::query()->find($other->getKey()))->toBeNull();
        });

        test("{$short}: refuses to query without a tenant context", function () use ($model) {
            app(TenantContext::class)->forget();

            $model::query()->get();
        })->throws(MissingTenantContext::class);

        test("{$short}: refuses to create a row for another tenant", function () use ($model, $make) {
            $template = $make();
            $copy = $template->replicate();
            $copy->setAttribute('tenant_id', Tenant::factory()->create()->id);
            $before = $model::query()->count();

            expect(fn () => $copy->save())->toThrow(CrossTenantWrite::class);
            expect($model::query()->count())->toBe($before);
        });

        test("{$short}: tenant_id cannot change", function () use ($make) {
            $row = $make();
            $row->setAttribute('tenant_id', Tenant::factory()->create()->id);

            $row->save();
        })->throws(CrossTenantWrite::class);
    }

    /**
     * @param  Closure(): Model  $make
     */
    public static function inOtherTenant(Closure $make): Model
    {
        return app(TenantContext::class)->run(Tenant::factory()->create(), fn () => $make());
    }
}

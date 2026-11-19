<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Api\OpenApiDocument;
use App\Audit\AuditLog;
use App\Audit\ChainVerifier;
use App\Auth\LoginThrottle;
use App\Authorization\PermissionCatalog;
use App\I18n\Locales;
use App\I18n\StringCatalog;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('projektrack:permissions:sync', function (PermissionCatalog $catalog) {
    $count = $catalog->sync();
    $this->info("{$count} permissions registered.");
})->purpose('Write the permissions of the core and all installed modules to the permission catalog');

Artisan::command('projektrack:audit:verify {--tenant= : Verify only this tenant id}', function (ChainVerifier $verifier) {
    $only = $this->option('tenant');
    $tenants = is_string($only) && $only !== '' ? [$only] : $verifier->tenants();
    $failures = 0;

    foreach ($tenants as $tenantId) {
        $result = $verifier->verify($tenantId);

        if ($result->intact) {
            $this->line("{$tenantId}: intact, {$result->checked} entries.");

            continue;
        }

        $failures++;
        $this->error("{$tenantId}: BROKEN at entry {$result->brokenAtSeq} ({$result->problem}), {$result->checked} entries intact before it.");
        Log::critical('Audit chain broken', ['tenant_id' => $tenantId, 'seq' => $result->brokenAtSeq, 'problem' => $result->problem]);
    }

    return $failures === 0 ? 0 : 1;
})->purpose('Recompute the audit hash chains and report the first broken entry');

Artisan::command('projektrack:strings:cache', function (StringCatalog $catalog, Locales $locales) {
    foreach ($catalog->compile($locales->available()) as $locale => $count) {
        $this->line("{$locale}: {$count} strings compiled.");
    }
})->purpose('Compile the shipped strings of every locale for fast loading');

Artisan::command('projektrack:strings:clear', function (StringCatalog $catalog) {
    $catalog->clear();
    $this->info('Compiled strings removed.');
})->purpose('Remove the compiled strings; they are read from the language files again');

Artisan::command('projektrack:auth:unlock {tenant : Slug of the tenant} {email : Address of the locked account}', function (TenantContext $context) {
    $tenant = Tenant::query()->where('slug', $this->argument('tenant'))->first();

    if (! $tenant instanceof Tenant) {
        $this->error('No tenant with this slug.');

        return 1;
    }

    // S-0903: an operator lifts a sign-in lock; the step is audited, the address is not.
    $context->run($tenant, function () {
        $email = $this->argument('email');
        app(LoginThrottle::class)->clear(is_string($email) ? $email : '');
        app(AuditLog::class)->record('auth.unlocked');
    });
    $this->info('Sign-in lock lifted.');

    return 0;
})->purpose('Lift the sign-in lock of an account after failed attempts');

Artisan::command('projektrack:api:openapi {--check : Fail if the committed document is out of date or a route is undocumented}', function (OpenApiDocument $document) {
    $missing = $document->undocumented();

    if ($missing !== []) {
        $this->error('API routes without #[ApiOperation]: '.implode(', ', $missing));

        return 1;
    }

    $path = base_path(OpenApiDocument::PATH);
    $json = $document->json();

    if ($this->option('check')) {
        if (! is_file($path) || file_get_contents($path) !== $json) {
            $this->error(OpenApiDocument::PATH.' is out of date: run php artisan projektrack:api:openapi and commit it.');

            return 1;
        }

        $this->info(OpenApiDocument::PATH.' is current.');

        return 0;
    }

    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, $json);
    $this->info('Written '.OpenApiDocument::PATH.'.');

    return 0;
})->purpose('Write the OpenAPI 3.1 document of /api/v1 (S-0913)');

// Security baseline 8.2: weekly verification; failures are logged as critical.
Schedule::command('projektrack:audit:verify')->weekly()->sundays()->at('03:00');

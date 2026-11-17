<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Audit\ChainVerifier;
use App\Authorization\PermissionCatalog;
use App\I18n\Locales;
use App\I18n\StringCatalog;
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

// Security baseline 8.2: weekly verification; failures are logged as critical.
Schedule::command('projektrack:audit:verify')->weekly()->sundays()->at('03:00');

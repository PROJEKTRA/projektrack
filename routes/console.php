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
use App\Doctor\Doctor;
use App\Doctor\Finding;
use App\I18n\Locales;
use App\I18n\StringCatalog;
use App\Models\Tenant;
use App\Release\ReleaseVerifier;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

Artisan::command('projektrack:doctor {--external= : Public URL to check; the report of the admin page stays as it is} {--requested : Run only when an administrator asked for a check}', function (Doctor $doctor) {
    // The request is taken only by a run that really starts (S-0988).
    if ($this->option('requested') && Cache::pull(Doctor::REQUESTED_KEY) !== true) {
        return 0;
    }
    $external = $this->option('external');
    $findings = is_string($external) && $external !== '' ? $doctor->run($external, store: false) : $doctor->run();
    $critical = 0;

    foreach ($findings as $finding) {
        $status = $finding->ok ? 'OK  ' : strtoupper($finding->severity);
        $this->line(sprintf('%-9s %s%s', $status, __($finding->title()), $finding->detail !== null ? " ($finding->detail)" : ''));

        if (! $finding->ok) {
            $this->line('          '.__($finding->fix()));
            $critical += $finding->severity === Finding::CRITICAL ? 1 : 0;
        }
    }

    // Non-zero on critical findings, so operators can run it from their monitoring.
    return $critical === 0 ? 0 : 1;
})->purpose('Check this installation from outside: headers, robots.txt, error pages, web root (S-0988)');

Artisan::command('projektrack:release:verify {file : Release file, for example projektrack-0.9.0.zip} {--sums= : Checksum list (default: SHA256SUMS next to the file)} {--signature= : Signature of the list (default: SHA256SUMS.sig next to it)}', function () {
    $file = $this->argument('file');
    if (! is_string($file) || $file === '') {
        $this->error('Give the release file.');

        return 1;
    }
    $dir = dirname($file);
    $sums = $this->option('sums');
    $signature = $this->option('signature');
    $keys = config('release.public_keys');

    $verifier = new ReleaseVerifier(is_array($keys) ? array_values(array_filter($keys, 'is_string')) : []);
    $problem = $verifier->verify(
        $file,
        is_string($sums) && $sums !== '' ? $sums : $dir.'/SHA256SUMS',
        is_string($signature) && $signature !== '' ? $signature : $dir.'/SHA256SUMS.sig',
    );

    if ($problem !== null) {
        $this->error('Refused: '.$problem);

        return 1;
    }

    $this->info(basename($file).' is a genuine release.');

    return 0;
})->purpose('Check the signature and checksum of a release file before installing it (S-0924)');

// S-0988: daily, so the admin page and banner are current, and within a minute
// of "Check now" (no queue worker needed on plain hosting).
Schedule::command('projektrack:doctor')->dailyAt('04:30')->withoutOverlapping(10);
Schedule::command('projektrack:doctor --requested')->everyMinute()->withoutOverlapping(10);

// Security baseline 8.2: weekly verification; failures are logged as critical.
Schedule::command('projektrack:audit:verify')->weekly()->sundays()->at('03:00');

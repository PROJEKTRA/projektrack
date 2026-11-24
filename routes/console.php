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
use App\Backup\BackupFile;
use App\Backup\Backups;
use App\Doctor\Doctor;
use App\Doctor\DoctorAccess;
use App\Doctor\Finding;
use App\I18n\Locales;
use App\I18n\StringCatalog;
use App\Licensing\InstanceId;
use App\Licensing\InstanceLicense;
use App\Licensing\InvalidLicense;
use App\Models\Tenant;
use App\Modules\EditionNotLicensed;
use App\Modules\ModuleRegistry;
use App\Modules\TenantModules;
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

// Backups on plain hosting. The secret key is shown once and never stored here.
Artisan::command('projektrack:backup:key', function () {
    $keys = BackupFile::generateKeys();
    $this->line('In .env:');
    $this->line('BACKUP_PUBLIC_KEY='.$keys['public']);
    $this->newLine();
    $this->warn('Keep this secret key away from the server (password vault). Without it no backup can be read:');
    $this->line($keys['secret']);
})->purpose('Create the key pair for encrypted backups');

Artisan::command('projektrack:backup', function (Backups $backups) {
    $key = config('backup.public_key');
    if (! is_string($key) || $key === '') {
        $this->error('BACKUP_PUBLIC_KEY is empty. Run projektrack:backup:key first.');

        return 1;
    }
    try {
        $file = $backups->create($key);
    } catch (RuntimeException $e) {
        Log::error('Backup failed', ['reason' => $e->getMessage()]);
        $this->error($e->getMessage());

        return 1;
    }
    $backups->prune(config()->integer('backup.keep_days'));
    foreach (Tenant::query()->get() as $tenant) {
        app(TenantContext::class)->run($tenant, fn () => app(AuditLog::class)->record('backup.created', null, ['file' => basename($file)]));
    }
    $this->info('Written '.$file.'. Copy it off the server.');
    $this->line('SHA-256 '.hash_file('sha256', $file).' (note it elsewhere: restore only a file with this value)');

    return 0;
})->purpose('Back up database and files into one encrypted file');

Artisan::command('projektrack:restore {file : Backup file} {--key-file= : File with the secret key; delete it afterwards}', function (Backups $backups) {
    $file = $this->argument('file');
    $keyFile = $this->option('key-file');
    if (! is_string($file) || ! is_string($keyFile) || ! is_file($keyFile)) {
        $this->error('Give the backup file and --key-file.');

        return 1;
    }
    try {
        $backups->restore($file, (string) file_get_contents($keyFile));
    } catch (RuntimeException $e) {
        $this->error($e->getMessage());

        return 1;
    }
    foreach (Tenant::query()->get() as $tenant) {
        app(TenantContext::class)->run($tenant, fn () => app(AuditLog::class)->record('backup.restored', null, ['file' => basename($file)]));
    }
    $this->call('projektrack:audit:verify');
    $this->info('Restored. API tokens and shared links are expired. Use the .env of the old installation (APP_KEY), then delete the key file.');

    return 0;
})->purpose('Restore a backup into an empty installation of the same version');

Schedule::command('projektrack:backup')->dailyAt('02:30')->withoutOverlapping(120)->when(fn (): bool => config('backup.public_key') !== '');

// A module that arrived with an update, for one existing company (S-0939). New companies start with all of them.
Artisan::command('projektrack:modules:enable {tenant : Slug of the tenant} {module : Key of the module, for example crm}', function (TenantContext $context, ModuleRegistry $registry) {
    $tenant = Tenant::query()->where('slug', $this->argument('tenant'))->first();
    $key = $this->argument('module');
    if ($tenant === null || ! is_string($key) || ! $registry->has($key)) {
        $this->error('Unknown tenant or module.');

        return 1;
    }

    try {
        $plan = $context->run($tenant, fn (): array => app(TenantModules::class)->enable($key));
    } catch (EditionNotLicensed $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $this->info($plan === [] ? 'Already on.' : 'Switched on: '.implode(', ', $plan));

    return 0;
})->purpose('Switch on a module for a tenant, with everything it requires');

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

Artisan::command('projektrack:release:verify {file : Release file, for example projektrack-0.9.0.zip} {--sums= : Checksum list (default: SHA256SUMS next to the file)} {--signature= : Signature of the list (default: SHA256SUMS.sig next to it)} {--release= : Expected version: the file must be exactly that release}', function () {
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
        is_string($this->option('release')) && $this->option('release') !== '' ? $this->option('release') : null,
    );

    if ($problem !== null) {
        $this->error('Refused: '.$problem);

        return 1;
    }

    $this->info(basename($file).' is a genuine release.');

    return 0;
})->purpose('Check the signature and checksum of a release file before installing it (S-0924)');

Artisan::command('projektrack:license:install {file : License file}', function (InstanceLicense $licenses, DoctorAccess $operator, TenantContext $context) {
    $file = $this->argument('file');
    $token = is_string($file) && is_file($file) && filesize($file) <= 65536 ? file_get_contents($file) : false;

    if (! is_string($token)) {
        $this->error('The license file is not readable.');

        return 1;
    }

    try {
        $license = $licenses->install($token);
    } catch (InvalidLicense $e) {
        $this->error('Not installed: '.$e->getMessage());

        return 1;
    }

    $tenant = $operator->operatorTenant();
    Log::notice('License installed', ['license_id' => $license->id, 'serial' => $license->serial]);
    if ($tenant === null) {
        $this->warn('Not recorded in an audit log: set DOCTOR_OPERATOR_TENANT to the tenant that operates this installation.');
    } else {
        $context->run($tenant, fn () => app(AuditLog::class)->record('license.installed', ['instance_licenses', $license->id], ['serial' => $license->serial, 'edition' => $license->edition->value]));
    }
    $this->info("License {$license->id} (issue {$license->serial}) installed for {$license->customer}.");

    return 0;
})->purpose('Validate a license file offline and install it (S-0974)');

Artisan::command('projektrack:license:show', function (InstanceLicense $licenses, InstanceId $instance) {
    try {
        $this->line('Instance: '.$instance->get());
    } catch (InvalidLicense $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $license = $licenses->current();
    if ($licenses->problem() !== null) {
        $this->error('The installed license is not valid: '.$licenses->problem());
        $this->line('Status:   invalid');
    }
    $this->line('Edition:  '.($license->edition->value ?? 'community'));

    if ($license !== null) {
        $this->line('Customer: '.$license->customer);
        $this->line('License:  '.$license->id.', issue '.$license->serial);
        $this->line('Seats:    '.$license->fullSeats.' full, '.$license->lightSeats.' light');
        $this->line('Expires:  '.$license->expiresAt->toIso8601ZuluString());
        $this->line('Status:   '.($licenses->status()->value ?? ''));
    }
    if ($licenses->clockProblem()) {
        $this->warn('The system clock is far behind a time seen earlier. Check it.');
    }

    return 0;
})->purpose('Show the instance id and the installed license (S-0974)');

// S-0988: daily, so the admin page and banner are current, and within a minute
// of "Check now" (no queue worker needed on plain hosting).
Schedule::command('projektrack:doctor')->dailyAt('04:30')->withoutOverlapping(10);
Schedule::command('projektrack:doctor --requested')->everyMinute()->withoutOverlapping(10);

// S-0974: the license is validated anew and the clock watched; problems are logged as critical.
Schedule::call(function (InstanceLicense $licenses): void {
    try {
        if ($licenses->problem() !== null) {
            Log::critical('The installed license is not valid', ['reason' => $licenses->problem()]);
        }
        if ($licenses->clockProblem()) {
            Log::critical('The system clock is far behind a time seen earlier');
        }
    } catch (InvalidLicense $e) {
        Log::critical($e->getMessage());
    }
})->name('license-check')->hourly();

// Plain hosting (S-0925): no worker runs, so the one cron entry drains the queue.
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()->withoutOverlapping(5)->when(fn (): bool => config('queue.default') === 'database');

// Security baseline 8.2: weekly verification; failures are logged as critical.
Schedule::command('projektrack:audit:verify')->weekly()->sundays()->at('03:00');

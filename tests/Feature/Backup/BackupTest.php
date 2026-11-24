<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Backup\BackupFile;
use App\Backup\Backups;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Projects\Domain\WorkPackage;

/*
 * Backups on plain hosting (part of S-0927 and S-0928).
 */

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/ptk-backup-'.bin2hex(random_bytes(6));
    mkdir($this->dir.'/app', 0700, true);
    mkdir($this->dir.'/framework', 0700, true);
    $this->app->useStoragePath($this->dir);
    $this->keys = BackupFile::generateKeys();
});

afterEach(fn () => File::deleteDirectory($this->dir));

function emptyInstallation(): void
{
    DB::table('work_packages')->update(['parent_id' => null]);
    foreach (array_reverse(app(Backups::class)->tables()) as $table) {
        DB::table($table)->delete();
    }
}

it('reads back exactly what was written, only with the key', function (int $size) {
    $plain = $size === 0 ? '' : random_bytes($size);
    file_put_contents($this->dir.'/a', $plain);
    BackupFile::encrypt($this->dir.'/a', $this->dir.'/b', $this->keys['public']);
    BackupFile::decrypt($this->dir.'/b', $this->dir.'/c', $this->keys['secret']);
    expect(file_get_contents($this->dir.'/c'))->toBe($plain);
    if ($size > 100) {
        expect(str_contains((string) file_get_contents($this->dir.'/b'), substr($plain, 0, 64)))->toBeFalse();
    }
    expect(fn () => BackupFile::decrypt($this->dir.'/b', $this->dir.'/d', BackupFile::generateKeys()['secret']))->toThrow(RuntimeException::class, 'does not fit');
})->with([0, 1, 65535, 65536, 65537, 200000]);

it('notices changed and cut off files', function () {
    file_put_contents($this->dir.'/a', random_bytes(150000));
    BackupFile::encrypt($this->dir.'/a', $this->dir.'/b', $this->keys['public']);
    $bytes = (string) file_get_contents($this->dir.'/b');
    file_put_contents($this->dir.'/cut', substr($bytes, 0, 8 + 80 + 24 + 65553));
    $bytes[70000] = chr(ord($bytes[70000]) ^ 1);
    file_put_contents($this->dir.'/changed', $bytes);
    file_put_contents($this->dir.'/other', 'no backup');
    foreach (['cut', 'changed', 'other'] as $i => $name) {
        expect(fn () => BackupFile::decrypt("{$this->dir}/{$name}", "{$this->dir}/out{$i}", $this->keys['secret']))->toThrow(RuntimeException::class);
    }
});

it('restores every table and file into an empty installation', function () {
    config(['backup.public_key' => $this->keys['public']]);
    $this->artisan('migrate');
    app()['env'] = 'testing';
    $this->artisan('projektrack:demo', ['tenant' => $this->tenant->slug])->assertSuccessful();
    $first = WorkPackage::withoutGlobalScopes()->firstOrFail();
    DB::table('work_packages')->where('id', '!=', $first->id)->where('project_id', $first->project_id)->update(['parent_id' => $first->id]);
    file_put_contents($this->dir.'/app/stored', 'content');
    $count = fn (): array => collect(Schema::getTableListing(null, false))->reject(fn (string $t): bool => in_array($t, ['migrations', 'sqlite_sequence', 'jobs', 'cache', 'sessions', 'cache_locks', 'job_batches', 'failed_jobs', 'password_reset_tokens'], true))->mapWithKeys(fn (string $t): array => [$t => [DB::table($t)->count(), md5((string) json_encode(DB::table($t)->get()->sortBy(fn ($r): string => (string) json_encode($r))->values()))]])->sortKeys()->all();
    $before = $count();

    $this->artisan('projektrack:backup')->assertSuccessful();
    $file = (glob($this->dir.'/backups/*.ptkbak') ?: [])[0];
    expect(str_contains((string) file_get_contents($file), 'example.test'))->toBeFalse();
    file_put_contents($this->dir.'/key', $this->keys['secret']);
    $this->artisan('projektrack:restore', ['file' => $file, '--key-file' => $this->dir.'/key'])->expectsOutputToContain('has data')->assertFailed();

    emptyInstallation();
    unlink($this->dir.'/app/stored');
    $this->artisan('projektrack:restore', ['file' => $file, '--key-file' => $this->dir.'/key'])->assertSuccessful();
    expect($count())->toBe($before)->and(file_get_contents($this->dir.'/app/stored'))->toBe('content');
})->skip(fn (): bool => DB::connection()->getDriverName() !== 'sqlite', 'deletes inside the test transaction; other databases are covered by hand');

it('refuses a backup of another version and keeps the newest backup', function () {
    $file = app(Backups::class)->create($this->keys['public']);
    DB::table('migrations')->insert(['migration' => '2099_01_01_000000_new', 'batch' => 99]);
    emptyInstallation();
    expect(fn () => app(Backups::class)->restore($file, $this->keys['secret']))->toThrow(RuntimeException::class, 'another version');
    touch($file, time() - 40 * 86400);
    expect(app(Backups::class)->prune(14))->toBe(0);
    file_put_contents($this->dir.'/backups/backup-29990101-000000.ptkbak', 'x');
    expect(app(Backups::class)->prune(14))->toBe(1);
});

it('does nothing without a key', function () {
    config(['backup.public_key' => '']);
    $this->artisan('projektrack:backup')->assertFailed();
    expect(User::query()->count())->toBe(0)->and(is_dir($this->dir.'/backups'))->toBeFalse();
});

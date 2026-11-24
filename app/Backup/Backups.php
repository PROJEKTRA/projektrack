<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Pdo\Mysql;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ValueError;
use ZipArchive;

/**
 * Backup and restore on plain hosting, in PHP only (part of S-0927 and S-0928):
 * every table and every stored file, as one encrypted file.
 */
final class Backups
{
    /** Tables that are rebuilt or short-lived. */
    private const array SKIP = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'sqlite_sequence'];

    /** @var resource|null */
    private $lock = null;

    public function folder(): string
    {
        return storage_path('backups');
    }

    public function create(string $publicKey): string
    {
        $this->ensureSupported();
        $work = $this->workFolder();
        $zip = null;
        try {
            $zipFile = $work.'/backup.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('The backup could not be started.');
            }
            $migrations = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
            $tables = $this->tables();
            if (! in_array('tenants', $tables, true)) {
                throw new RuntimeException('The database has no tables of this application.');
            }
            // One picture of all tables, whatever the server has as default.
            if (in_array($this->connection()->getDriverName(), ['mysql', 'mariadb'], true) && DB::transactionLevel() === 0) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $counts = DB::transaction(function () use ($work, $zip, $tables): array {
                $counts = [];
                foreach ($tables as $table) {
                    $file = "{$work}/{$table}.jsonl";
                    $counts[$table] = $this->dump($table, $file);
                    $zip->addFile($file, "tables/{$table}.jsonl");
                }

                return $counts;
            });
            $base = storage_path('app');
            foreach (is_dir($base) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)) : [] as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && ! $file->isLink()) {
                    $zip->addFile($file->getPathname(), 'files/'.str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1)));
                }
            }
            $zip->addFromString('manifest.json', json_encode(['format' => 1, 'created_at' => now()->toIso8601ZuluString(), 'driver' => $this->connection()->getDriverName(), 'migrations' => $migrations, 'tables' => $counts], JSON_THROW_ON_ERROR));
            if (! $zip->close()) {
                throw new RuntimeException('The backup could not be written. Is the disk full?');
            }
            File::ensureDirectoryExists($this->folder(), 0700);
            $target = $this->folder().'/backup-'.now()->format('Ymd-His').'.ptkbak';
            $free = disk_free_space($this->folder());
            if ($free !== false && $free < filesize($zipFile) * 1.1) {
                throw new RuntimeException('The disk is full.');
            }
            // Whole or not at all: an unfinished file never looks like a backup.
            try {
                BackupFile::encrypt($zipFile, $target.'.part', $publicKey);
                if (! rename($target.'.part', $target)) {
                    throw new RuntimeException('The backup could not be written.');
                }
            } finally {
                @unlink($target.'.part');
            }

            return $target;
        } finally {
            $this->release($zip, $work);
        }
    }

    /** Delete backups older than the days, but always keep the newest. */
    public function prune(int $days): int
    {
        $files = glob($this->folder().'/backup-*.ptkbak') ?: [];
        sort($files);
        array_pop($files);
        $deleted = 0;
        foreach ($files as $file) {
            if (filemtime($file) < now()->subDays($days)->getTimestamp()) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /** Restore into an empty, migrated installation of the same version. */
    public function restore(string $file, string $secretKey): void
    {
        if (DB::table('tenants')->exists()) {
            throw new RuntimeException('This installation has data. Restore into an empty one only.');
        }
        $this->ensureSupported();
        $work = $this->workFolder();
        $zip = null;
        try {
            BackupFile::decrypt($file, $work.'/backup.zip', $secretKey);
            $zip = new ZipArchive;
            if ($zip->open($work.'/backup.zip', ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('The backup is damaged.');
            }
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            $mine = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
            if (! is_array($manifest) || ! is_array($manifest['tables'] ?? null) || ($manifest['migrations'] ?? null) !== $mine) {
                throw new RuntimeException('The backup is of another version. Install that version first.');
            }
            $tables = $this->tables();
            $family = fn (mixed $driver): string => is_string($driver) && in_array($driver, ['mysql', 'mariadb'], true) ? 'mysql' : (is_string($driver) ? $driver : '');
            if ($family($manifest['driver'] ?? null) !== $family($this->connection()->getDriverName())) {
                throw new RuntimeException('The backup is of another kind of database.');
            }
            $this->checkNames($zip);
            // Files last, inside the transaction: if they fail, the database stays empty too.
            DB::transaction(function () use ($zip, $tables, $manifest): void {
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete();
                }
                foreach ($tables as $table) {
                    $rows = $this->load($zip, $table);
                    if ($rows !== ($manifest['tables'][$table] ?? 0)) {
                        throw new RuntimeException("The backup of {$table} is not complete.");
                    }
                }
                // Access that was taken away since the backup does not come back.
                DB::table('users')->update(['remember_token' => null]);
                foreach (['api_tokens', 'shared_links'] as $table) {
                    if (Schema::hasColumn($table, 'expires_at')) {
                        DB::table($table)->update(['expires_at' => now()->subSecond()]);
                    }
                }
                $this->files($zip);
            });
        } finally {
            $this->release($zip, $work);
        }
    }

    /** @return list<string> tables of a backup, parents before children */
    public function tables(): array
    {
        $needs = [];
        foreach (Schema::getTableListing($this->connection()->getDriverName() === 'sqlite' ? null : $this->connection()->getDatabaseName(), false) as $table) {
            if ($table !== 'migrations' && ! in_array($table, self::SKIP, true)) {
                $needs[$table] = array_values(array_unique(array_filter(array_map(fn (array $k): string => $k['foreign_table'], Schema::getForeignKeys($table)), fn (string $t): bool => $t !== $table)));
            }
        }
        ksort($needs);
        $ordered = [];
        while ($needs !== []) {
            $ready = array_keys(array_filter($needs, fn (array $n): bool => array_diff($n, $ordered) === []));
            if ($ready === []) {
                throw new RuntimeException('The tables refer to each other in a circle: '.implode(', ', array_keys($needs)));
            }
            foreach ($ready as $table) {
                $ordered[] = $table;
                unset($needs[$table]);
            }
        }

        return $ordered;
    }

    private function dump(string $table, string $file): int
    {
        $out = fopen($file, 'xb');
        if ($out === false) {
            throw new RuntimeException('The backup could not be written.');
        }
        $count = 0;
        // Unbuffered: a large table never sits in memory as a whole.
        $pdo = $this->connection()->getPdo();
        $mysql = in_array($this->connection()->getDriverName(), ['mysql', 'mariadb'], true);
        if ($mysql) {
            $pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, false);
        }
        foreach (DB::table($table)->cursor() as $row) {
            $line = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
            if (fwrite($out, $line) !== strlen($line)) {
                throw new RuntimeException('The disk is full.');
            }
            $count++;
        }
        if ($mysql) {
            $pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, true);
        }
        if (! fclose($out)) {
            throw new RuntimeException('The disk is full.');
        }

        return $count;
    }

    private function load(ZipArchive $zip, string $table): int
    {
        $in = $zip->getStream("tables/{$table}.jsonl");
        if ($in === false) {
            return 0;
        }
        // Rows of one table can refer to each other: first without, then with.
        $self = [];
        foreach (Schema::getForeignKeys($table) as $key) {
            if ($key['foreign_table'] === $table) {
                $self = [...$self, ...array_diff($key['columns'], ['tenant_id'])];
            }
        }
        $count = 0;
        $batch = [];
        $later = [];
        $flush = function () use (&$batch, $table): void {
            if ($batch !== []) {
                DB::table($table)->insert($batch);
                $batch = [];
            }
        };
        while (($line = fgets($in)) !== false) {
            $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($row)) {
                throw new RuntimeException("The backup of {$table} is damaged.");
            }
            foreach ($self as $column) {
                if (($row[$column] ?? null) !== null) {
                    $later[] = [$row['id'] ?? null, $column, $row[$column]];
                    $row[$column] = null;
                }
            }
            $batch[] = $row;
            $count++;
            if (count($batch) >= 50) {
                $flush();
            }
        }
        $flush();
        foreach ($later as [$id, $column, $value]) {
            DB::table($table)->where('id', $id)->update([$column => $value]);
        }

        return $count;
    }

    /** @return list<string> */
    private function names(ZipArchive $zip): array
    {
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_starts_with($name, 'files/')) {
                $names[] = substr($name, 6);
            }
        }

        return $names;
    }

    /** Never outside the folder, whatever the archive says. */
    private function checkNames(ZipArchive $zip): void
    {
        foreach ($this->names($zip) as $path) {
            if ($path === '' || str_contains($path, '\\') || preg_match('#(^|/)\.\.(/|$)|^/|:#', $path) === 1) {
                throw new RuntimeException('The backup holds a wrong file name.');
            }
        }
    }

    private function files(ZipArchive $zip): void
    {
        $base = storage_path('app');
        foreach ($this->names($zip) as $path) {
            File::ensureDirectoryExists(dirname("{$base}/{$path}"));
            $in = $zip->getStream('files/'.$path);
            if ($in === false || file_put_contents("{$base}/{$path}", $in) === false) {
                throw new RuntimeException("{$path} could not be restored.");
            }
        }
    }

    private function ensureSupported(): void
    {
        if ($this->connection()->getDriverName() === 'pgsql') {
            throw new RuntimeException('Not on PostgreSQL; use the Docker tools.');
        }
    }

    /**
     * A folder for the plain text while the backup is made, one run at a time.
     * A run that was killed leaves nothing behind for longer than the next run.
     */
    private function workFolder(): string
    {
        File::ensureDirectoryExists(storage_path('framework'));
        $lock = fopen(storage_path('framework/backup.lock'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another backup or restore is running.');
        }
        $this->lock = $lock;
        foreach (glob(storage_path('framework/backup-*'), GLOB_ONLYDIR) ?: [] as $old) {
            File::deleteDirectory($old);
        }
        $work = storage_path('framework/backup-'.bin2hex(random_bytes(8)));
        File::makeDirectory($work, 0700, true);
        // Also after a fatal error, where "finally" does not run.
        register_shutdown_function(fn () => is_dir($work) && File::deleteDirectory($work));

        return $work;
    }

    private function release(?ZipArchive $zip, string $work): void
    {
        // An open archive cannot be deleted on every system.
        try {
            $zip?->close();
        } catch (ValueError) {
            // Closed already.
        }
        File::deleteDirectory($work);
        if ($this->lock !== null) {
            fclose($this->lock);
            $this->lock = null;
        }
    }

    private function connection(): Connection
    {
        return DB::connection();
    }
}

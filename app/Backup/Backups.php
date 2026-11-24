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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Backup and restore on plain hosting, in PHP only (part of S-0927 and S-0928):
 * every table and every stored file, as one encrypted file.
 */
final class Backups
{
    /** Tables that are rebuilt or short-lived. */
    private const array SKIP = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'sqlite_sequence'];

    public function folder(): string
    {
        return storage_path('backups');
    }

    public function create(string $publicKey): string
    {
        $work = $this->workFolder();
        try {
            $zipFile = $work.'/backup.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('The backup could not be started.');
            }
            $migrations = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
            // One picture of all tables.
            $counts = DB::transaction(function () use ($work, $zip): array {
                $counts = [];
                foreach ($this->tables() as $table) {
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
            BackupFile::encrypt($zipFile, $target, $publicKey);

            return $target;
        } finally {
            File::deleteDirectory($work);
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
        if ($this->connection()->getDriverName() === 'pgsql') {
            throw new RuntimeException('Restore on PostgreSQL is not supported; use the Docker tools.');
        }
        $work = $this->workFolder();
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
            });
            $this->files($zip);
        } finally {
            File::deleteDirectory($work);
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
        foreach (DB::table($table)->cursor() as $row) {
            fwrite($out, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
            $count++;
        }
        fclose($out);

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

    private function files(ZipArchive $zip): void
    {
        $base = storage_path('app');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! str_starts_with($name, 'files/')) {
                continue;
            }
            $path = substr($name, 6);
            // Never outside the folder, whatever the archive says.
            if ($path === '' || str_contains($path, '\\') || preg_match('#(^|/)\.\.(/|$)|^/|:#', $path) === 1) {
                throw new RuntimeException('The backup holds a wrong file name.');
            }
            File::ensureDirectoryExists(dirname("{$base}/{$path}"));
            $in = $zip->getStream($name);
            if ($in === false || file_put_contents("{$base}/{$path}", $in) === false) {
                throw new RuntimeException("{$path} could not be restored.");
            }
        }
    }

    private function workFolder(): string
    {
        $work = storage_path('framework/backup-'.bin2hex(random_bytes(8)));
        File::makeDirectory($work, 0700, true);

        return $work;
    }

    private function connection(): Connection
    {
        return DB::connection();
    }
}

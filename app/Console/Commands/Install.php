<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Installs on plain hosting from the release ZIP (S-0925, without the web
 * installer). The first run writes .env with a generated key; after the
 * operator filled it in, the second run checks the requirements, migrates and
 * creates the tenant with its first administrator. Running it again is safe
 * and is also how a newer ZIP is taken into use.
 */
class Install extends Command
{
    private const array EXTENSIONS = ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo', 'session', 'sodium', 'tokenizer', 'xml'];

    /** Values of the template that must be replaced. */
    private const string PLACEHOLDER = 'CHANGE-ME';

    protected $signature = 'projektrack:install
        {--slug= : Short technical name of the company, for example "acme"}
        {--name= : Company name shown to users}
        {--admin-email= : Address of the first administrator}
        {--admin-name= : Name of the first administrator}
        {--locale=bs : Default language}
        {--country=BA : Country code (ISO 3166-1 alpha-2)}
        {--currency=BAM : Base currency (ISO 4217)}
        {--timezone=Europe/Sarajevo : Time zone}';

    protected $description = 'Install on plain hosting, or take a newer release into use';

    public function handle(): int
    {
        $env = $this->laravel->environmentFilePath();

        if (! is_file($env)) {
            return $this->writeEnvironment($env);
        }

        $problems = $this->problems();
        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }
            $this->line('Nothing was changed. Fix this in '.$env.' or on the server and run the command again.');

            return self::FAILURE;
        }

        foreach (['migrate' => ['--force' => true], 'projektrack:permissions:sync' => [], 'projektrack:strings:cache' => []] as $command => $arguments) {
            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $this->error("{$command} failed. Fix the cause and run the command again.");

                return self::FAILURE;
            }
        }

        if (Tenant::query()->exists()) {
            $this->info('Installed. The data is kept; nothing else was changed.');

            return self::SUCCESS;
        }

        $host = parse_url(config()->string('app.url'), PHP_URL_HOST);

        return $this->call('projektrack:tenant:create', [
            '--domain' => is_string($host) ? $host : '',
            ...array_combine(
                ['--slug', '--name', '--admin-email', '--admin-name', '--locale', '--country', '--currency', '--timezone'],
                array_map(fn (string $name) => $this->option($name), ['slug', 'name', 'admin-email', 'admin-name', 'locale', 'country', 'currency', 'timezone']),
            ),
        ]);
    }

    private function writeEnvironment(string $env): int
    {
        $template = file_get_contents(base_path('deploy/amp/env.example'));
        $key = 'base64:'.base64_encode(Encrypter::generateKey(config()->string('app.cipher')));

        // Never over an existing file, and readable by its owner only.
        $handle = is_string($template) ? @fopen($env, 'x') : false;
        if ($handle === false || ! is_string($template)) {
            $this->error("{$env} cannot be written.");

            return self::FAILURE;
        }
        @chmod($env, 0600);
        fwrite($handle, str_replace('APP_KEY=', 'APP_KEY='.$key, $template));
        fclose($handle);

        $this->info("Written {$env}.");
        $this->line('Replace every '.self::PLACEHOLDER.' in it, keep a copy of APP_KEY in a safe place, and run the command again.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function problems(): array
    {
        $problems = [];
        $extensions = [...self::EXTENSIONS, match (config('database.default')) {
            'mysql', 'mariadb' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            default => 'pdo_sqlite',
        }];

        foreach ($extensions as $extension) {
            if (! extension_loaded($extension)) {
                $problems[] = "The PHP extension {$extension} is missing. Enable it in the hosting panel.";
            }
        }

        foreach ([storage_path('app'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $folder) {
            if (! is_dir($folder) || ! is_writable($folder)) {
                $problems[] = "The folder {$folder} is missing or not writable.";
            }
        }

        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            $problems[] = 'APP_KEY is empty.';
        }

        $url = config('app.url');
        $parts = is_string($url) ? parse_url($url) : false;
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
        $local = in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.test');
        if ($host === '' || str_contains($host, self::PLACEHOLDER)) {
            $problems[] = 'APP_URL is not the address of this installation.';
        } elseif ($scheme !== 'https' && ! $local) {
            $problems[] = 'APP_URL must start with https://.';
        }
        if (config('app.debug') === true && ! $local) {
            $problems[] = 'APP_DEBUG must be false.';
        }

        if ($problems === []) {
            try {
                DB::connection()->getPdo();
            } catch (Throwable) {
                // The message of the driver can hold the account; it is not shown.
                $problems[] = 'The database does not answer. Check the DB_ values.';
            }
        }

        return $problems;
    }
}

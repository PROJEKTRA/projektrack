<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Doctor;

use App\Http\Middleware\SetSecurityHeaders;
use App\Models\Tenant;
use App\Tenancy\TenantUrls;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * `projektrack doctor` (S-0988, security baseline 6.3): checks a live
 * installation from outside, as a visitor would see it, plus the settings
 * that must hold on a public server. The last report is kept for the admin
 * page.
 */
final class Doctor
{
    public const string CACHE_KEY = 'doctor.report';

    /** Set by "Check now"; the scheduler runs the check within a minute. */
    public const string REQUESTED_KEY = 'doctor.requested';

    /** What must never answer 200 from the web root. */
    public const array PRIVATE_PATHS = [
        '/.env',
        '/.git/HEAD',
        '/.git/config',
        '/composer.json',
        '/artisan',
        '/storage/logs/laravel.log',
        '/vendor/autoload.php',
        '/database/database.sqlite',
    ];

    /** Words and patterns no anonymous response may contain (INV-09). */
    public const array FINGERPRINTS = ['/Projektrack/i', '/PROJEKTRA/', '/Laravel/i', '/Symfony/i', '/\bVite\b/', '/\bInertia\b/', '/\bPHP\/\d/', '/X-Debug/i'];

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly DoctorAccess $access,
        private readonly TenantUrls $urls,
    ) {}

    /**
     * Checks $base, or the primary host of the operator tenant (APP_URL is
     * usually not a tenant host, so its sign-in page would be a bare 404).
     * Without $store the admin page keeps its report (a check of another URL).
     *
     * @return list<Finding>
     */
    public function run(?string $base = null, bool $store = true): array
    {
        $base = rtrim($base ?? $this->defaultBase(), '/');
        $local = $this->isLocal($base);
        $findings = [
            new Finding('debug', Finding::CRITICAL, config('app.debug') !== true || $local),
            new Finding('environment', Finding::WARNING, app()->environment('production') || $local),
            new Finding('app_key', Finding::CRITICAL, is_string(config('app.key')) && config('app.key') !== ''),
            new Finding('https', Finding::WARNING, str_starts_with($base, 'https://') || $local),
            new Finding('storage', Finding::CRITICAL, is_writable(storage_path('framework')) && is_writable(storage_path('logs'))),
        ];

        $page = $this->get($base.'/login');
        if ($page === null || ! $page->successful()) {
            $findings[] = new Finding('reachable', Finding::CRITICAL, false, $page === null ? '/login' : "/login {$page->status()}");
        } else {
            $findings[] = new Finding('reachable', Finding::CRITICAL, true);
            array_push($findings, ...$this->headers('page', $page, $base));
            $findings[] = $this->noFingerprint('page', $page);
        }

        $asset = $this->assetPath();
        $assetResponse = $asset !== null ? $this->get($base.$asset) : null;
        if ($assetResponse !== null) {
            $findings[] = new Finding('asset_headers', Finding::WARNING, $this->robotsHeaderOk($assetResponse), $asset);
        }

        $findings[] = $this->robots($base);
        $missing = $this->get($base.'/'.Str::lower(Str::random(16)));
        $findings[] = $this->notFound($missing);
        if ($missing !== null) {
            $findings[] = $this->noFingerprint('not_found', $missing);
        }
        $findings[] = $this->privatePaths($base);
        $findings[] = $this->health($base);

        if ($store) {
            $this->store($base, $findings);
        }

        return $findings;
    }

    /**
     * The last report, or null before the first run.
     *
     * @return array{at: string, base: string, findings: list<Finding>}|null
     */
    public function last(): ?array
    {
        $report = $this->cache->get(self::CACHE_KEY);

        if (! is_array($report) || ! is_array($report['findings'] ?? null)) {
            return null;
        }

        $findings = [];
        foreach ($report['findings'] as $item) {
            if (is_array($item) && is_string($item['check'] ?? null) && is_string($item['severity'] ?? null) && is_bool($item['ok'] ?? null)) {
                $detail = $item['detail'] ?? null;
                $findings[] = new Finding($item['check'], $item['severity'], $item['ok'], is_string($detail) ? $detail : null);
            }
        }

        return [
            'at' => is_string($report['at'] ?? null) ? $report['at'] : '',
            'base' => is_string($report['base'] ?? null) ? $report['base'] : '',
            'findings' => $findings,
        ];
    }

    /** Critical findings of the last report, for the admin banner. */
    public function criticalProblems(): int
    {
        $last = $this->last();

        return $last === null ? 0 : count($this->critical($last['findings']));
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<string>
     */
    private function critical(array $findings): array
    {
        return array_values(array_map(
            fn (Finding $f): string => $f->check,
            array_filter($findings, fn (Finding $f): bool => ! $f->ok && $f->severity === Finding::CRITICAL),
        ));
    }

    /** @param list<Finding> $findings */
    private function store(string $base, array $findings): void
    {
        $before = $this->criticalProblems();
        $critical = $this->critical($findings);

        $this->cache->forever(self::CACHE_KEY, [
            'at' => now()->toIso8601String(),
            'base' => $base,
            'findings' => array_map(fn (Finding $f): array => $f->toArray(), $findings),
        ]);

        // New critical problems reach the log, where monitoring picks them up.
        if ($before === 0 && $critical !== []) {
            Log::critical('System check found critical problems.', ['checks' => $critical]);
        }
    }

    private function defaultBase(): string
    {
        $operator = $this->access->operatorTenant();

        // A shared installation must name its operator, else nobody sees the report.
        if ($operator === null && Tenant::query()->count() > 1) {
            Log::warning('System check: several tenants and no DOCTOR_OPERATOR_TENANT; the report is shown to nobody.');
        }

        return ($operator !== null ? $this->urls->root($operator) : null) ?? config()->string('app.url');
    }

    /** A development address, where debug output and plain HTTP are expected. */
    private function isLocal(string $base): bool
    {
        $host = strtolower(trim((string) parse_url($base, PHP_URL_HOST), '[]'));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }

    /** @return list<Finding> */
    private function headers(string $what, Response $response, string $base): array
    {
        $server = (string) $response->header('Server');

        return [
            new Finding('robots_header', Finding::CRITICAL, $this->robotsHeaderOk($response), $what),
            new Finding('referrer_policy', Finding::CRITICAL, $response->header('Referrer-Policy') === 'no-referrer', $what),
            new Finding('powered_by', Finding::WARNING, $response->header('X-Powered-By') === '', $what),
            new Finding('server_signature', Finding::WARNING, preg_match('/\d/', $server) !== 1, $what),
            new Finding('csp', Finding::CRITICAL, str_contains((string) $response->header('Content-Security-Policy'), "frame-ancestors 'none'"), $what),
            new Finding('hsts', Finding::WARNING, ! str_starts_with($base, 'https://') || str_contains((string) $response->header('Strict-Transport-Security'), 'max-age='), $what),
        ];
    }

    private function robotsHeaderOk(Response $response): bool
    {
        $header = (string) $response->header('X-Robots-Tag');
        $wanted = array_map('trim', explode(',', SetSecurityHeaders::ROBOTS));

        return array_diff($wanted, array_map('trim', explode(',', $header))) === [];
    }

    private function noFingerprint(string $what, Response $response): Finding
    {
        $body = $response->body();

        foreach (self::FINGERPRINTS as $pattern) {
            if (preg_match($pattern, $body) === 1) {
                return new Finding('fingerprint', Finding::WARNING, false, $what);
            }
        }

        return new Finding('fingerprint', Finding::WARNING, true, $what);
    }

    private function robots(string $base): Finding
    {
        $response = $this->get($base.'/robots.txt');
        $body = $response !== null ? str_replace("\r\n", "\n", trim($response->body())) : null;

        return new Finding('robots_txt', Finding::CRITICAL, $body === "User-agent: *\nDisallow: /");
    }

    private function notFound(?Response $response): Finding
    {
        $leaks = $response === null
            || $response->status() !== 404
            || str_contains($response->body(), base_path())
            || preg_match('/(Stack trace|#0 |\.php:\d+)/', $response->body()) === 1;

        return new Finding('error_pages', Finding::CRITICAL, ! $leaks);
    }

    private function privatePaths(string $base): Finding
    {
        foreach (self::PRIVATE_PATHS as $path) {
            $response = $this->get($base.$path);

            if ($response !== null && $response->status() === 200) {
                return new Finding('web_root', Finding::CRITICAL, false, $path);
            }
        }

        return new Finding('web_root', Finding::CRITICAL, true);
    }

    private function health(string $base): Finding
    {
        $response = $this->get($base.'/health');
        // A 404 only counts when HEALTH_ALLOWED_IPS keeps the doctor out.
        $allowed = config('health.allowed_ips');
        $restricted = is_string($allowed) && trim($allowed) !== '';

        return new Finding('health', Finding::WARNING, $response !== null && ($response->body() === 'OK' || ($restricted && $response->status() === 404)));
    }

    /** A hashed script of the build, to check headers of static files. */
    private function assetPath(): ?string
    {
        $manifest = public_path('build/manifest.json');
        $data = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;

        if (! is_array($data)) {
            return null;
        }

        foreach ($data as $entry) {
            if (is_array($entry) && is_string($entry['file'] ?? null) && str_ends_with($entry['file'], '.js')) {
                return '/build/'.$entry['file'];
            }
        }

        return null;
    }

    private function get(string $url): ?Response
    {
        try {
            return $this->http->withoutRedirecting()->connectTimeout(5)->timeout(5)->withHeaders(['Accept' => 'text/html'])->get($url);
        } catch (Throwable) {
            return null;
        }
    }
}

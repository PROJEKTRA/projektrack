<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\I18n;

use App\Modules\ModuleRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Shipped strings per locale (ADR-0005 section 3): `lang/<locale>/<component>.json`
 * for core and `<module>/lang/<locale>/<module key>.json` for each installed
 * module. Every file is a flat object `{ "key": "ICU message" }`; the
 * identifier is `<component>:<key>`.
 *
 * When a compiled file exists under the cache path it is used as is, so a
 * production request reads one OPcache-held array per locale and no JSON.
 */
final class StringCatalog
{
    public const string IDENTIFIER = '/^[a-z][a-z0-9_]*:[a-z][a-z0-9_]*(\.[a-z0-9_]+)*$/';

    private const int MAX_KEY_LENGTH = 100;

    /** @var array<string, array{version: string, messages: array<string, string>}> */
    private array $loaded = [];

    public function __construct(
        private readonly string $langPath,
        private readonly ModuleRegistry $modules,
        private readonly string $cachePath,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, string> identifier => ICU message
     */
    public function messages(string $locale): array
    {
        return $this->load($locale)['messages'];
    }

    public function message(string $locale, string $identifier): ?string
    {
        return $this->load($locale)['messages'][$identifier] ?? null;
    }

    /** Changes whenever any string of the locale changes. */
    public function version(string $locale): string
    {
        return $this->load($locale)['version'];
    }

    /**
     * Write the compiled file for each locale.
     *
     * @param  list<string>  $locales
     * @return array<string, int> locale => number of strings
     */
    public function compile(array $locales): array
    {
        if (! is_dir($this->cachePath) && ! mkdir($this->cachePath, 0755, true) && ! is_dir($this->cachePath)) {
            throw new RuntimeException("Cannot create {$this->cachePath}");
        }

        $counts = [];
        foreach ($locales as $locale) {
            unset($this->loaded[$locale]);
            $compiled = $this->fromSource($locale);
            $target = $this->compiledFile($locale);
            $temporary = $target.'.'.bin2hex(random_bytes(4)).'.tmp';

            file_put_contents($temporary, '<?php return '.var_export($compiled, true).';'.PHP_EOL, LOCK_EX);
            rename($temporary, $target);

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($target, true);
            }

            $this->loaded[$locale] = $compiled;
            $counts[$locale] = count($compiled['messages']);
        }

        return $counts;
    }

    public function clear(): void
    {
        foreach (glob($this->cachePath.'/*.php') ?: [] as $file) {
            unlink($file);
        }

        $this->loaded = [];
    }

    /**
     * @return array{version: string, messages: array<string, string>}
     */
    private function load(string $locale): array
    {
        if (isset($this->loaded[$locale])) {
            return $this->loaded[$locale];
        }

        $file = $this->compiledFile($locale);
        if (is_file($file)) {
            $compiled = require $file;
            if (is_array($compiled) && is_string($compiled['version'] ?? null) && is_array($compiled['messages'] ?? null)) {
                /** @var array{version: string, messages: array<string, string>} $compiled */
                return $this->loaded[$locale] = $compiled;
            }
        }

        return $this->loaded[$locale] = $this->fromSource($locale);
    }

    /**
     * @return array{version: string, messages: array<string, string>}
     */
    private function fromSource(string $locale): array
    {
        if (! preg_match('/^[a-z]{2,3}(-[A-Z0-9]{2,4})?$/', $locale)) {
            return ['version' => hash('xxh128', $locale), 'messages' => []];
        }

        $messages = [];

        foreach (glob($this->langPath.'/'.$locale.'/*.json') ?: [] as $file) {
            $this->merge($messages, $file, basename($file, '.json'), null);
        }

        foreach ($this->modules->all() as $key => $manifest) {
            $file = dirname($manifest->path).'/lang/'.$locale.'/'.$key.'.json';
            if (is_file($file)) {
                $this->merge($messages, $file, $key, $key);
            }
        }

        ksort($messages);

        return [
            'version' => hash('xxh128', $locale."\0".json_encode($messages, JSON_THROW_ON_ERROR)),
            'messages' => $messages,
        ];
    }

    /**
     * @param  array<string, string>  $messages
     */
    private function merge(array &$messages, string $file, string $component, ?string $requiredComponent): void
    {
        if ($requiredComponent !== null && $component !== $requiredComponent) {
            return;
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            $this->logger->error('String file is not a JSON object', ['file' => $file]);

            return;
        }

        foreach ($data as $key => $message) {
            $identifier = $component.':'.$key;

            if (! is_string($message) || strlen((string) $key) > self::MAX_KEY_LENGTH || ! preg_match(self::IDENTIFIER, $identifier)) {
                $this->logger->error('Invalid string entry skipped', ['file' => $file, 'key' => $key]);

                continue;
            }

            $messages[$identifier] = $message;
        }
    }

    private function compiledFile(string $locale): string
    {
        return $this->cachePath.'/'.$locale.'.php';
    }
}

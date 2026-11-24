<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Modules;

use Psr\Log\LoggerInterface;

/**
 * Discovers module manifests, rejects invalid ones and orders the rest so every
 * module boots after the modules it requires (ADR-0002).
 *
 * A rejected module never boots; the reason is logged so an operator can fix
 * it. Rejection cascades: a module that requires a rejected one is rejected too.
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleManifest> in boot order */
    private array $modules = [];

    /** @var array<string, list<string>> path => problems */
    private array $rejected = [];

    /**
     * @param  list<string>  $searchPaths  directories that contain one folder per module
     */
    public function __construct(private readonly array $searchPaths, private readonly LoggerInterface $logger, private readonly bool $vendor = true)
    {
        $this->discover();
    }

    /**
     * @return array<string, ModuleManifest> keyed by module key, in boot order
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function has(string $key): bool
    {
        return isset($this->modules[$key]);
    }

    public function get(string $key): ModuleManifest
    {
        return $this->modules[$key] ?? throw new UnknownModule($key);
    }

    /**
     * @return array<string, list<string>> path of each rejected manifest and why
     */
    public function rejected(): array
    {
        return $this->rejected;
    }

    /**
     * Modules that must be enabled together with $key, dependencies first,
     * $key last. Already enabled modules are left out.
     *
     * @param  list<string>  $enabled
     * @return list<string>
     *
     * @throws ModuleConflict when an enabled module conflicts with one in the plan
     */
    public function enablePlan(string $key, array $enabled): array
    {
        $needed = [];
        $this->collectRequirements($key, $needed);

        $plan = array_values(array_filter(
            array_keys($this->modules),
            fn (string $k): bool => isset($needed[$k]) && ! in_array($k, $enabled, true),
        ));

        $after = array_merge($enabled, $plan);
        foreach ($plan as $planned) {
            foreach ($after as $other) {
                if (in_array($other, $this->get($planned)->conflicts, true)
                    || in_array($planned, $this->get($other)->conflicts, true)) {
                    throw new ModuleConflict($planned, $other);
                }
            }
        }

        return $plan;
    }

    /**
     * Enabled modules that require $key directly or indirectly. Disabling $key
     * is refused while this list is not empty.
     *
     * @param  list<string>  $enabled
     * @return list<string>
     */
    public function dependentsOf(string $key, array $enabled): array
    {
        $this->get($key);

        return array_values(array_filter(
            $enabled,
            function (string $candidate) use ($key): bool {
                if ($candidate === $key || ! $this->has($candidate)) {
                    return false;
                }
                $needed = [];
                $this->collectRequirements($candidate, $needed);

                return isset($needed[$key]);
            },
        ));
    }

    /**
     * @param  array<string, true>  $needed
     */
    private function collectRequirements(string $key, array &$needed): void
    {
        if (isset($needed[$key])) {
            return;
        }

        $needed[$key] = true;

        foreach ($this->get($key)->requires as $required) {
            $this->collectRequirements($required, $needed);
        }
    }

    private function discover(): void
    {
        /** @var array<string, ModuleManifest> $found */
        $found = [];

        foreach ($this->searchPaths as $searchPath) {
            foreach (glob(rtrim($searchPath, '/\\').'/*/module.json') ?: [] as $file) {
                try {
                    $manifest = ModuleManifest::fromFile($file);
                } catch (InvalidModuleManifest $e) {
                    $this->reject($e->path, $e->problems);

                    continue;
                }

                // Modules of the licensor do not exist on any other instance.
                if ($manifest->edition === Edition::Vendor && ! $this->vendor) {
                    continue;
                }

                if (isset($found[$manifest->key])) {
                    $this->reject($manifest->path, ["module key \"{$manifest->key}\" is already used by {$found[$manifest->key]->path}"]);

                    continue;
                }

                $found[$manifest->key] = $manifest;
            }
        }

        $this->modules = $this->order($this->dropUnresolvable($found));
    }

    /**
     * @param  array<string, ModuleManifest>  $found
     * @return array<string, ModuleManifest>
     */
    private function dropUnresolvable(array $found): array
    {
        do {
            $changed = false;
            foreach ($found as $key => $manifest) {
                $missing = array_values(array_diff($manifest->requires, array_keys($found)));
                if ($missing !== []) {
                    $this->reject($manifest->path, ['"requires" names unknown or rejected modules: '.implode(', ', $missing)]);
                    unset($found[$key]);
                    $changed = true;
                }
            }
        } while ($changed);

        return $found;
    }

    /**
     * Topological order (Kahn). Modules that form a cycle are rejected.
     *
     * @param  array<string, ModuleManifest>  $found
     * @return array<string, ModuleManifest>
     */
    private function order(array $found): array
    {
        ksort($found);

        $ordered = [];
        $remaining = $found;

        while ($remaining !== []) {
            $ready = array_filter(
                $remaining,
                fn (ModuleManifest $m): bool => array_diff($m->requires, array_keys($ordered)) === [],
            );

            if ($ready === []) {
                foreach ($remaining as $manifest) {
                    $this->reject($manifest->path, ['"requires" forms a dependency cycle']);
                }

                break;
            }

            foreach ($ready as $key => $manifest) {
                $ordered[$key] = $manifest;
                unset($remaining[$key]);
            }
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $problems
     */
    private function reject(string $path, array $problems): void
    {
        $this->rejected[$path] = $problems;
        $this->logger->error('Module rejected', ['path' => $path, 'problems' => $problems]);
    }
}

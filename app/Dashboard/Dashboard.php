<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Dashboard;

use App\Models\User;
use App\Modules\TenantModules;
use Closure;

/**
 * Figures on the dashboard. Modules add their own; each answers only for
 * users who may see it, and a switched-off module answers nothing.
 */
final class Dashboard
{
    /** @var list<array{module: string, cards: Closure(User): list<array{label: string, value: string, href: string, alert?: bool}>}> */
    private array $sources = [];

    /** @param Closure(User): list<array{label: string, value: string, href: string, alert?: bool}> $cards */
    public function add(string $module, Closure $cards): void
    {
        $this->sources[] = ['module' => $module, 'cards' => $cards];
    }

    /** @return list<array{label: string, value: string, href: string, alert?: bool}> */
    public function for(User $user): array
    {
        $modules = app(TenantModules::class);
        $cards = [];
        foreach ($this->sources as $source) {
            if ($modules->isEnabled($source['module'])) {
                array_push($cards, ...($source['cards'])($user));
            }
        }

        return $cards;
    }
}

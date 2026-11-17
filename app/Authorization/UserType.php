<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Authorization;

/**
 * License type of a user (ADR-0003).
 *
 * Full and Light are derived from the effective permissions, never chosen, so
 * the type cannot be gamed by labeling. Portal and Service are account kinds.
 */
enum UserType: string
{
    case Full = 'full';
    case Light = 'light';
    case Portal = 'portal';
    case Service = 'service';

    /**
     * The Light set: time, expenses, own tasks and approvals. Kept in the core,
     * not in module manifests, because it decides what a customer pays.
     */
    public const array LIGHT_PERMISSIONS = [
        'core.files.upload',
        'timesheets.view',
        'timesheets.book',
        'timesheets.approve',
        'expenses.view',
        'expenses.submit',
        'expenses.approve',
        'absences.request',
        'absences.approve',
        'work.view',
        'work.transition',
        'work.comment',
    ];

    public static function isLight(string $permission): bool
    {
        return in_array($permission, self::LIGHT_PERMISSIONS, true);
    }

    /**
     * Portal accounts (customer contacts) never hold internal permissions. Portal
     * features arrive with their modules (sign-off, service desk) and extend this.
     *
     * @return list<string>
     */
    public static function portalPermissions(): array
    {
        return [];
    }

    /**
     * Whether a user of this type may hold $permission at all.
     */
    public function allows(string $permission): bool
    {
        return match ($this) {
            self::Full, self::Service => true,
            self::Light => self::isLight($permission),
            self::Portal => in_array($permission, self::portalPermissions(), true),
        };
    }

    public function isDerived(): bool
    {
        return $this === self::Full || $this === self::Light;
    }
}

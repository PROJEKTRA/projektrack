<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Settings\Settings;

/**
 * Whether a user gets a notification type by e-mail (S-0912). In-app
 * notifications always arrive. E-mail is on by default; each user may turn
 * it off per type, and an admin may turn it off for the organization
 * (setting core.email_notifications). Mandatory types are always e-mailed.
 */
final class NotificationPreferences
{
    public const string CHANNEL = 'mail';

    public function __construct(
        private readonly NotificationTypes $types,
        private readonly Settings $settings,
    ) {}

    public function wantsMail(User $user, string $type): bool
    {
        if ($this->types->get($type)['mandatory'] ?? false) {
            return true;
        }

        if ($this->settings->get('core.email_notifications') !== true) {
            return false;
        }

        return ! NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('channel', self::CHANNEL)
            ->where('frequency', 'off')
            ->exists();
    }

    /**
     * Each type with the user's e-mail choice.
     *
     * @return list<array{type: string, label: string, mandatory: bool, mail: bool}>
     */
    public function of(User $user): array
    {
        $off = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('channel', self::CHANNEL)
            ->where('frequency', 'off')
            ->pluck('type')
            ->all();

        return array_map(fn (array $type): array => [
            ...$type,
            'mail' => $type['mandatory'] || ! in_array($type['type'], $off, true),
        ], $this->types->all());
    }

    /**
     * Store the user's e-mail choice per type; mandatory and unknown types
     * are ignored.
     *
     * @param  array<string, bool>  $mail
     */
    public function save(User $user, array $mail): void
    {
        foreach ($this->types->all() as $type) {
            if ($type['mandatory'] || ! array_key_exists($type['type'], $mail)) {
                continue;
            }

            $query = NotificationPreference::query()
                ->where('user_id', $user->id)
                ->where('type', $type['type'])
                ->where('channel', self::CHANNEL);

            if ($mail[$type['type']]) {
                $query->delete();
            } elseif (! $query->exists()) {
                NotificationPreference::query()->create([
                    'user_id' => $user->id,
                    'type' => $type['type'],
                    'channel' => self::CHANNEL,
                    'frequency' => 'off',
                ]);
            }
        }
    }
}

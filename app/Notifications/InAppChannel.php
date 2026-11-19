<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use App\Models\AppNotification;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Stores a notification for the bell and the notification list (S-0912),
 * in the recipient's tenant.
 */
final class InAppChannel
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function send(User $notifiable, AppNotice $notice): void
    {
        $subject = $notice->subject();
        $store = fn (): AppNotification => AppNotification::query()->create([
            'user_id' => $notifiable->id,
            'type' => $notice->type(),
            'subject_type' => $subject[0] ?? null,
            'subject_id' => $subject[1] ?? null,
            'data' => $notice->toInApp($notifiable),
        ]);

        if ($this->tenants->has() && $this->tenants->id() === $notifiable->tenant_id) {
            $store();

            return;
        }

        $this->tenants->run($notifiable->tenant()->firstOrFail(), $store);
    }
}

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user that an admin removed their two-factor setup, so an unexpected
 * reset is noticed at once.
 */
class TwoFactorWasReset extends Notification
{
    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? app()->getLocale();
        $brand = config()->string('app.name');

        return (new MailMessage)
            ->subject(__('two_factor.reset_mail.subject', ['brand' => $brand], $locale))
            ->greeting(__('two_factor.reset_mail.greeting', ['name' => $notifiable->name], $locale))
            ->line(__('two_factor.reset_mail.body', [], $locale))
            ->line(__('two_factor.reset_mail.not_you', [], $locale))
            ->salutation(__('two_factor.reset_mail.salutation', ['brand' => $brand], $locale));
    }
}

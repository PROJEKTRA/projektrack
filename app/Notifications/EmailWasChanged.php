<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use App\I18n\Strings;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the former address that the account uses another one now. Never
 * names the new address.
 */
class EmailWasChanged extends Notification
{
    public function __construct(private readonly User $user) {}

    /** @return list<string> */
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        return AppNotice::mailAs($this->user, function (): MailMessage {
            $strings = app(Strings::class);
            $locale = $this->user->preferredLocale();

            return (new MailMessage)
                ->subject($strings->get('core:email_changed_mail.subject', [], $locale))
                ->greeting($strings->get('core:two_factor.reset_mail.greeting', AppNotice::escapeMarkdown(['name' => $this->user->name]), $locale))
                ->line($strings->get('core:email_changed_mail.body', [], $locale))
                ->line($strings->get('core:two_factor.reset_mail.not_you', [], $locale))
                ->salutation($strings->get('core:two_factor.reset_mail.salutation', [], $locale));
        });
    }
}

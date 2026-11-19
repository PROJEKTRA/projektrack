<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use App\I18n\Strings;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells a user that an admin removed their two-factor setup, so an unexpected
 * reset is noticed at once. A security notice: always e-mailed (S-0912).
 */
class TwoFactorWasReset extends AppNotice
{
    public function type(): string
    {
        return 'core.two_factor_reset';
    }

    public function message(): string
    {
        return 'core:two_factor.reset_notice';
    }

    public function url(): ?string
    {
        return '/settings/security';
    }

    /**
     * Written in the recipient's language chain, whatever the sender's
     * language is.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $strings = app(Strings::class);
        $locale = $notifiable->preferredLocale();

        return (new MailMessage)
            ->subject($strings->get('core:two_factor.reset_mail.subject', [], $locale))
            ->greeting($strings->get('core:two_factor.reset_mail.greeting', ['name' => $notifiable->name], $locale))
            ->line($strings->get('core:two_factor.reset_mail.body', [], $locale))
            ->line($strings->get('core:two_factor.reset_mail.not_you', [], $locale))
            ->salutation($strings->get('core:two_factor.reset_mail.salutation', [], $locale));
    }
}

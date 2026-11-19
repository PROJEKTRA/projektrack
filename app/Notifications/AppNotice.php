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
 * Base of every notification to users (S-0912): always in-app, by e-mail
 * unless the user turned the type off. Texts are string identifiers,
 * written in the recipient's language. E-mails carry no tracking pixel and
 * links exactly as given.
 */
abstract class AppNotice extends Notification
{
    /** Declared type, for example core.two_factor_reset (NotificationTypes). */
    abstract public function type(): string;

    /** Identifier of the one-line message shown in-app and as the e-mail body. */
    abstract public function message(): string;

    /** @return array<string, scalar> */
    public function arguments(): array
    {
        return [];
    }

    /** Where the notification leads (relative URL), if anywhere. */
    public function url(): ?string
    {
        return null;
    }

    /** @return array{0: string, 1: string}|null subject type and id */
    public function subject(): ?array
    {
        return null;
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $channels = [InAppChannel::class];

        if (app(NotificationPreferences::class)->wantsMail($notifiable, $this->type())) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array{message: string, args: array<string, scalar>, url: string|null} */
    public function toInApp(User $notifiable): array
    {
        return ['message' => $this->message(), 'args' => $this->arguments(), 'url' => $this->url()];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $strings = app(Strings::class);
        $locale = $notifiable->preferredLocale();
        $mail = (new MailMessage)
            ->subject($strings->get('core:notifications.mail.subject', ['type' => $strings->get($this->label(), [], $locale)], $locale))
            ->greeting($strings->get('core:notifications.mail.greeting', ['name' => $notifiable->name], $locale))
            ->line($strings->get($this->message(), $this->arguments(), $locale));

        if ($this->url() !== null) {
            $mail->action($strings->get('core:notifications.mail.open', [], $locale), url($this->url()));
        }

        return $mail
            ->line($strings->get('core:notifications.mail.settings', [], $locale))
            ->salutation($strings->get('core:notifications.mail.salutation', [], $locale));
    }

    /** Label of the type, from its declaration. */
    protected function label(): string
    {
        return app(NotificationTypes::class)->get($this->type())['label'] ?? 'core:notifications.types.other';
    }
}

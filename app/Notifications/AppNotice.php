<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use App\I18n\Strings;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantUrls;
use Closure;
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
        // The recipient's tenant decides: its modules, its e-mail switch, the user's choices.
        return self::asRecipient($notifiable, function () use ($notifiable): array {
            $channels = [InAppChannel::class];

            if (app(NotificationPreferences::class)->wantsMail($notifiable, $this->type())) {
                $channels[] = 'mail';
            }

            return $channels;
        });
    }

    /**
     * Run in the recipient's tenant, also when a job or another tenant sends.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function asRecipient(User $notifiable, Closure $callback): mixed
    {
        $tenants = app(TenantContext::class);

        if ($tenants->has() && $tenants->id() === $notifiable->tenant_id) {
            return $callback();
        }

        return $tenants->run($notifiable->tenant()->firstOrFail(), fn (): mixed => $callback());
    }

    /**
     * Build an e-mail in the recipient's tenant, with links to its host.
     *
     * @param  Closure(): MailMessage  $build
     */
    public static function mailAs(User $notifiable, Closure $build): MailMessage
    {
        return self::asRecipient($notifiable, fn (): MailMessage => app(TenantUrls::class)->within(
            $notifiable->tenant()->firstOrFail(),
            // Action links are built here, while the tenant's host is the root.
            fn (): MailMessage => $build(),
        ));
    }

    /** @return array{message: string, args: array<string, scalar>, url: string|null} */
    public function toInApp(User $notifiable): array
    {
        return ['message' => $this->message(), 'args' => $this->arguments(), 'url' => $this->url()];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return self::mailAs($notifiable, fn (): MailMessage => $this->buildMail($notifiable));
    }

    private function buildMail(User $notifiable): MailMessage
    {
        $strings = app(Strings::class);
        $locale = $notifiable->preferredLocale();
        $mail = (new MailMessage)
            ->subject($strings->get('core:notifications.mail.subject', ['type' => $strings->get($this->label(), [], $locale)], $locale))
            ->greeting($strings->get('core:notifications.mail.greeting', ['name' => $notifiable->name], $locale))
            ->line($strings->get($this->message(), self::escapeMarkdown($this->arguments()), $locale));

        if ($this->url() !== null) {
            $mail->action($strings->get('core:notifications.mail.open', [], $locale), url($this->url()));
        }

        return $mail
            ->line($strings->get('core:notifications.mail.settings', [], $locale))
            ->salutation($strings->get('core:notifications.mail.salutation', [], $locale));
    }

    /**
     * Arguments are user data (names, titles); e-mail lines are Markdown, so a
     * name like "[Verify](https://evil.example)" must stay text, not a link.
     *
     * @param  array<string, scalar>  $arguments
     * @return array<string, scalar>
     */
    public static function escapeMarkdown(array $arguments): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value) ? (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', $value) : $value,
            $arguments,
        );
    }

    /** Label of the type, from its declaration. */
    protected function label(): string
    {
        return app(NotificationTypes::class)->get($this->type())['label'] ?? 'core:notifications.types.other';
    }
}

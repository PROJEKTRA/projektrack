<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password reset link, sent from the queue (S-0903): the request returns
 * as fast for a known address as for an unknown one, so its timing does not
 * reveal accounts. Single use, valid for auth.passwords.users.expire minutes.
 * The queued job is encrypted, so the token is readable neither in the queue
 * store nor in failed jobs (S-0923).
 */
class ResetPasswordLink extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * The link leads to the recipient's tenant host, where its token is valid,
     * also when the queue sends it.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $build = fn (): MailMessage => $this->buildMailMessage($this->resetUrl($notifiable));

        return $notifiable instanceof User ? AppNotice::mailAs($notifiable, $build) : $build();
    }
}

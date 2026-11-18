<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The password reset link, sent from the queue (S-0903): the request returns
 * as fast for a known address as for an unknown one, so its timing does not
 * reveal accounts. Single use, valid for auth.passwords.users.expire minutes.
 */
class ResetPasswordLink extends ResetPassword implements ShouldQueue
{
    use Queueable;
}

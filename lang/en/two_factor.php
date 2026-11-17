<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

return [
    'required' => 'Your role requires two-factor authentication. Set it up to continue.',
    'cannot_disable' => 'Your role requires two-factor authentication, so it cannot be turned off.',
    'reset_done' => 'Two-factor authentication was reset. The user has been signed out and informed by e-mail.',
    'recovery_codes_shown_once' => 'Save these codes now. For your security they are shown only this once.',
    'recovery_codes_hidden' => 'Your recovery codes were shown when they were created. You have :count unused codes. If you lost them, create new ones; the old ones then stop working.',
    'reset_mail' => [
        'subject' => 'Your two-factor authentication for :brand was reset',
        'greeting' => 'Hello :name,',
        'body' => 'An administrator has reset two-factor authentication for your account. You have been signed out on all devices. When you next sign in, you may be asked to set up two-factor authentication again.',
        'not_you' => 'If you did not ask for this, contact your administrator right away.',
        'salutation' => 'Your :brand team',
    ],
];

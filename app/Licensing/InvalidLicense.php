<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Licensing;

use RuntimeException;

/** Why a license token is refused: one plain sentence for logs and the console, and a reason for the screen. */
final class InvalidLicense extends RuntimeException
{
    /** @param  'invalid'|'untrusted_key'|'other_instance'|'not_started'|'ended'|'older'  $reason  string core:license.refused.* */
    public function __construct(string $message, public readonly string $reason = 'invalid')
    {
        parent::__construct($message);
    }
}

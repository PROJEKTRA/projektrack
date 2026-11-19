<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Branding;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Every e-mail shows the tenant's sender name (S-0911, ADR-0006 section 9).
 * The address stays the instance's until custom sender domains are verified.
 */
final class SetMailSender
{
    public function __construct(private readonly Brand $brand) {}

    public function handle(MessageSending $event): void
    {
        $from = $event->message->getFrom();
        $address = $from[0] ?? null;

        if ($address instanceof Address) {
            $event->message->from(new Address($address->getAddress(), $this->brand->current()['senderName']));
        }
    }
}

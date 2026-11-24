<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * Proprietary. Not part of the Community Edition.
 */

namespace Modules\Licensing\Domain;

use App\Notifications\AppNotice;

class OrderNotice extends AppNotice
{
    public function __construct(
        private readonly string $text,
        private readonly string $number,
        private readonly string $orderId,
    ) {}

    public function type(): string
    {
        return 'licensing.order';
    }

    public function message(): string
    {
        return $this->text;
    }

    /** @return array<string, scalar> */
    public function arguments(): array
    {
        return ['number' => $this->number];
    }

    public function url(): string
    {
        return '/licensing/orders/'.$this->orderId;
    }
}

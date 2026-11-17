<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

it('has no public landing page and sends guests on to sign-in', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

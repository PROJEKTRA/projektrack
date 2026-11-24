<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests run inside a tenant served at "localhost" (see Tests\TestCase).
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../modules', '../ee/modules');

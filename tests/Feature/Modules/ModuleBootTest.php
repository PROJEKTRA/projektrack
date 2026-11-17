<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Modules\ModuleRegistry;

it('boots the application with every shipped module manifest valid', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry)->toBe(app(ModuleRegistry::class))
        ->and($registry->rejected())->toBe([]);
});

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Providers\AppServiceProvider;
use App\Providers\AuditServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\I18nServiceProvider;
use App\Providers\ModuleServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    TenancyServiceProvider::class,
    I18nServiceProvider::class,
    AuthorizationServiceProvider::class,
    AuditServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ModuleServiceProvider::class,
];

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * System check (S-0988): projektrack:doctor and Admin > System check.
 */
return [

    // Slug of the tenant whose administrators see the system check and its
    // banner; the report is about the whole installation. Empty: on an
    // installation with one tenant, its administrators; with several tenants,
    // nobody (the command still works).
    'operator_tenant' => env('DOCTOR_OPERATOR_TENANT', ''),

];

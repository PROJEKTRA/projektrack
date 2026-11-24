<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * Backups on plain hosting. Docker installations use deploy/docker/projektrack.
 */
return [

    // Public key from "php artisan projektrack:backup:key"; empty turns the daily backup off.
    'public_key' => env('BACKUP_PUBLIC_KEY', ''),

    'keep_days' => 14,

];

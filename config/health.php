<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * Health check (S-0986, INV-10): GET /health answers "OK" only.
 */
return [

    // Comma-separated addresses or CIDR ranges allowed to ask; empty: anyone.
    'allowed_ips' => env('HEALTH_ALLOWED_IPS', ''),

];

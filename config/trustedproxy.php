<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * Reverse proxies whose X-Forwarded-* headers are believed (S-0923): the
 * client address for rate limits and the health check, and the scheme for
 * secure cookies. Comma-separated addresses or CIDR ranges; empty: none.
 * Only list proxies that overwrite these headers, never a whole network.
 */
return [

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];

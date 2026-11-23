<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * Public keys of the licensor (S-0974, ADR-0003 section 3). They ship with the
 * release; on purpose no environment variable can add one. Entry:
 *
 *   ['kid' => 'ptk-2026-01', 'public_key' => '<base64 of 32 bytes>',
 *    'valid_from' => '2026-12-01T00:00:00Z', 'valid_until' => '2028-01-01T00:00:00Z',
 *    'status' => 'active'],    // active, retired (verifies what it signed) or revoked
 *
 * A license is accepted only if it was issued within the validity of its key.
 * Without keys every license is refused and the instance is Community.
 */
return [

    'keys' => [],

];

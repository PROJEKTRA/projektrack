<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * Password hashing (S-0903, security baseline section 2): Argon2id with
 * 64 MiB memory, time cost 4 and parallelism 1. Hosts with little memory may
 * lower the memory to 19 MiB (19456) and the time cost to 2, the OWASP
 * minimum. Hashes with other parameters or another algorithm (for example
 * bcrypt from an import) are still accepted and rehashed at the next sign-in.
 */
return [

    'driver' => 'argon2id',

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        // Accept hashes of another algorithm so they can be rehashed on sign-in.
        'verify' => false,
    ],

    'rehash_on_login' => true,

];

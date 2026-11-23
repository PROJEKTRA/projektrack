<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * The licensor (S-0911): owner of the product and of every license. Stored
 * once, here, and read through App\Branding\Licensor by the About screen,
 * license documents and license invoices. Part of the product, not an
 * instance setting: operators and tenants cannot change it.
 *
 * Values left null are not shown. They are filled from the company register
 * entry of the licensor, never invented.
 */
return [

    'name' => 'PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik',
    'short_name' => 'PROJEKTRA IT Consulting',
    'website' => 'https://projektra.net',

    'street' => 'Meljanac 1D',
    'postal_code' => null,
    'city' => 'Travnik',
    'country_code' => 'BA',

    // Jedinstveni identifikacioni broj (tax identification number).
    'jib' => '4236848020000',
    // Matični broj subjekta upisa (company register number).
    'mbs' => '51-01-0039-26',

    'email' => null,

];

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * Brand of the instance (S-0911, ADR-0006 section 8). A tenant's brand
 * profile (Admin > Brand) overrides each value; whatever it leaves empty
 * comes from here. This file is the only place the product name is written:
 * strings use {brand}, templates read App\Branding\Brand (CI check).
 */
return [

    'name' => env('BRAND_NAME', 'Projektrack'),

    // Name shown before sign-in when a tenant has none (S-0986): generic by
    // default, never the product name.
    'login_title' => env('BRAND_LOGIN_TITLE'),

    // Primary color as #rrggbb; null keeps the shipped theme.
    'primary_color' => env('BRAND_PRIMARY_COLOR'),

    // Display name of outgoing e-mail; null uses the brand name.
    'email_sender_name' => env('BRAND_EMAIL_SENDER_NAME'),

    // The endorsement line of the licensor (docs/ux/brand.md). Removing it is a
    // white label entitlement (ADR-0006); until licensing exists it stays on.
    'show_powered_by' => true,

];

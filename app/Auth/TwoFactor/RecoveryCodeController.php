<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Auth\TwoFactor;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController as FortifyController;

/**
 * Returns the recovery codes created in this session, exactly once. Stored
 * codes are hashes and can never be shown again; the user generates new ones.
 */
class RecoveryCodeController extends FortifyController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(app(RecoveryCodes::class)->pullNew());
    }
}

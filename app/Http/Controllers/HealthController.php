<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Health check for monitoring (S-0986, INV-10): plain "OK" or "Unavailable",
 * nothing about product, version or internals. Without a session or cookies.
 * HEALTH_ALLOWED_IPS (addresses or CIDR ranges) limits who may ask; anyone
 * else gets the same plain 404 as an unknown path.
 */
final class HealthController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $allowed = array_values(array_filter(array_map('trim', explode(',', config()->string('health.allowed_ips', '')))));

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            // The same answer as any unknown path.
            abort(404);
        }

        try {
            DB::connection()->select('select 1');
        } catch (Throwable) {
            return $this->plain('Unavailable', 503);
        }

        return $this->plain('OK', 200);
    }

    private function plain(string $body, int $status): Response
    {
        return new Response($body, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}

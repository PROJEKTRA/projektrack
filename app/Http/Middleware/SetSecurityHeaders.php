<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invisible by default (S-0985) and the web security baseline (S-0909).
 *
 * Runs as global middleware so every response carries these headers, including
 * redirects, error pages and file downloads. None of them can be disabled by
 * configuration: being unindexable is a product guarantee, not a setting.
 */
class SetSecurityHeaders
{
    // All six directives of INV-01.
    public const string ROBOTS = 'noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate';

    public function handle(Request $request, Closure $next): Response
    {
        // Every script and inline style must carry this nonce; nothing else may run.
        Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Robots-Tag', self::ROBOTS);
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        // Only a sandboxed, default-deny policy (file downloads) may replace the page policy.
        $own = (string) $headers->get('Content-Security-Policy', '');
        if (! (str_contains($own, 'sandbox') && str_contains($own, "default-src 'none'"))) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Do not reveal the platform (S-0986): PHP adds X-Powered-By below the response object.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }
        $headers->remove('X-Powered-By');
        $headers->remove('Server');

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();

        $script = ["'self'", "'nonce-{$nonce}'"];
        $style = ["'self'", "'nonce-{$nonce}'"];
        $connect = ["'self'"];

        // The local Vite dev server only exists in development.
        if (app()->environment('local') && Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $ws = preg_replace('#^http#', 'ws', $hot) ?? $hot;
            array_push($script, $hot);
            array_push($style, $hot, "'unsafe-inline'");
            array_push($connect, $hot, $ws);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "img-src 'self' data: blob:",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}

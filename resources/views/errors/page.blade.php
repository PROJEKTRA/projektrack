{{--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only

    Error page (S-0986, INV-08): status and a plain sentence in the visitor's
    language. Never a path, host, exception, trace, product or version, never
    a request to another server.
--}}
@php
    $status = isset($exception) && method_exists($exception, 'getStatusCode') ? (int) $exception->getStatusCode() : 500;
    $known = [403, 404, 419, 429, 503];
    $key = in_array($status, $known, true) ? (string) $status : ($status >= 500 ? '5xx' : '4xx');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app(\App\I18n\Strings::class)->direction(app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
        <meta name="referrer" content="no-referrer">
        <title>{{ __('core:errors.'.$key.'.title') }}</title>
        <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: ui-sans-serif, system-ui, sans-serif; background: #fafafa; color: #171717; }
            main { max-width: 32rem; padding-inline: 1.5rem; text-align: center; }
            .status { font-size: 0.875rem; color: #737373; margin-block-end: 0.5rem; }
            h1 { font-size: 1.25rem; margin-block: 0 0.75rem; }
            p { line-height: 1.5; margin: 0; }
            @media (prefers-color-scheme: dark) { body { background: #0a0a0a; color: #fafafa; } .status { color: #a3a3a3; } }
        </style>
    </head>
    <body>
        <main>
            <p class="status">{{ $status }}</p>
            <h1>{{ __('core:errors.'.$key.'.title') }}</h1>
            <p>{{ __('core:errors.'.$key.'.message') }}</p>
        </main>
    </body>
</html>
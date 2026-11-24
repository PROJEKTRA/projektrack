{{--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
--}}
<!DOCTYPE html>
@php($strings = $page['props']['strings'] ?? null)
<html lang="{{ $strings['locale'] ?? str_replace('_', '-', app()->getLocale()) }}" dir="{{ $strings['dir'] ?? 'ltr' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        {{-- CSP nonce for runtime <style> elements of libraries (S-0909); readable only through the nonce property --}}
        <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
        @if ($strings)
            {{-- String bundle of the user's language chain (S-0916), fetched in parallel with the scripts --}}
            <meta name="strings-bundle" content="{{ $strings['url'] }}" data-version="{{ $strings['version'] }}" data-locale="{{ $strings['locale'] }}" data-dir="{{ $strings['dir'] }}">
            <link rel="preload" href="{{ $strings['url'] }}" as="fetch" crossorigin="anonymous">
        @endif
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
        <meta name="referrer" content="no-referrer">

        {{-- Apply the system dark mode preference before first paint --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                const appearance = @json($appearance ?? 'system');

                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <style nonce="{{ Vite::cspNonce() }}">
            html {
                background-color: var(--background, #f5f7f7);
            }

            html.dark {
                background-color: var(--background, #000012);
            }
        </style>

        {{-- Brand color of the tenant (S-0911): only a validated #rrggbb reaches this block --}}
        @php($brandCss = app(\App\Branding\Brand::class)->themeCss())
        @if ($brandCss)
            <style nonce="{{ Vite::cspNonce() }}">{{ $brandCss }}</style>
        @endif

        {{-- The tenant's logo as icon (S-0911), else the shipped one --}}
        @php($brandIcon = app(\App\Branding\Brand::class)->logoUrl())
        @if ($brandIcon)
            <link rel="icon" href="{{ $brandIcon }}">
        @else
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts', str_contains($page['component'], '::') ? (is_dir(base_path('modules/'.explode('::', $page['component'])[0])) ? '' : 'ee/').'modules/'.str_replace('::', '/resources/js/pages/', $page['component']).'.vue' : "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ app(\App\Branding\Brand::class)->nameForRequest() ?: __('core:auth.login.page_title') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

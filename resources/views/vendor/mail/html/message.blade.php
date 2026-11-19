{{--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only

    Mail layout (S-0911): header and footer show the tenant's brand, never the
    configured application name. The body is Markdown that the framework has
    already rendered from escaped lines.
--}}
@php($brandName = app(\App\Branding\Brand::class)->name())
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $brandName }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ new \Illuminate\Support\HtmlString((string) $slot) }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ new \Illuminate\Support\HtmlString((string) $subcopy) }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $brandName }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
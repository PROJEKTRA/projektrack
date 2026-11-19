{{--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only

    Mail header (S-0912): the name as text. Never an image from another
    server, which would be a third-party request and a read receipt.
--}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{ trim(strip_tags((string) $slot)) }}
</a>
</td>
</tr>

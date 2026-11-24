{{--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
--}}
<style>
    body { font-size: 9pt; color: #000d0e; } h1 { font-size: 16pt; color: #0e285c; margin: 0 0 4mm; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; } th { background: #eef2f7; } th, td { padding: 1.4mm 1.6mm; border-bottom: 0.2mm solid #cccccc; vertical-align: top; text-align: start; }
    .num { text-align: {{ $end }}; white-space: nowrap; } .sign td { border: 0; padding-top: 18mm; width: 50%; }
</style>
<h1>{{ $t('service_reports:document.title') }} {{ $r['number'] }}</h1>
<table>
    <tr><td>{{ $t('service_reports:document.from') }}</td><td>{{ $r['seller'] }}</td><td>{{ $t('service_reports:document.customer') }}</td><td>{{ $r['company'] }}</td></tr>
    <tr><td>{{ $t('service_reports:document.project') }}</td><td>{{ $r['project'] }}</td><td>{{ $t('service_reports:document.period') }}</td><td>{{ $format->day($r['from']) }} - {{ $format->day($r['to']) }}</td></tr>
</table>
<table>
    <thead><tr><th>{{ $t('service_reports:column.date') }}</th><th>{{ $t('service_reports:column.user') }}</th><th>{{ $t('service_reports:column.package') }}</th><th>{{ $t('service_reports:column.description') }}</th><th class="num">{{ $t('service_reports:column.hours') }}</th>@if ($amounts)<th class="num">{{ $t('service_reports:column.rate') }}</th><th class="num">{{ $t('service_reports:column.amount') }}</th>@endif</tr></thead>
    <tbody>
    @foreach ($r['lines'] as $line)
        <tr><td>{{ $format->day($line['date']) }}</td><td>{{ $line['user'] }}</td><td>{{ $line['package'] }}</td><td>@if ($line['correction']){{ $t('service_reports:document.correction') }} @endif{{ $line['description'] }}</td><td class="num">{{ $hours($line['minutes']) }}</td>@if ($amounts)<td class="num">{{ $line['rate'] === null ? '' : $decimal($line['rate']) }}</td><td class="num">{{ $line['amount_minor'] === null ? '' : $format->money($line['amount_minor'], $line['currency']) }}</td>@endif</tr>
    @endforeach
    </tbody>
</table>
@foreach (['package', 'user'] as $by)
    <table style="page-break-inside: avoid">
        <thead><tr><th>{{ $by === 'package' ? $t('service_reports:document.per_package') : $t('service_reports:document.per_user') }}</th><th class="num">{{ $t('service_reports:column.hours') }}</th></tr></thead>
        @foreach ($r['totals'][$by] as $name => $minutes)
            <tr><td>{{ $name }}</td><td class="num">{{ $hours($minutes) }}</td></tr>
        @endforeach
        <tr><td><strong>{{ $t('service_reports:document.total') }}</strong></td><td class="num"><strong>{{ $hours($r['minutes']) }}</strong></td></tr>
    </table>
@endforeach
@foreach ($r['amounts'] as $currency => $minor)
    <p class="num"><strong>{{ $t('service_reports:document.amount') }}: {{ $format->money($minor, $currency) }}</strong></p>
@endforeach
@if ($r['unvalued'] > 0)<p>{{ $t('service_reports:document.unvalued', ['count' => $r['unvalued']]) }}</p>@endif
<table class="sign" style="page-break-inside: avoid"><tr><td>____________________________<br>{{ $r['seller'] }}</td><td>____________________________<br>{{ $r['company'] }}</td></tr></table>

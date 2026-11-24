{{--
    Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
    SPDX-License-Identifier: AGPL-3.0-only
--}}
<style>
    body { font-size: 9pt; color: #000d0e; } h1 { font-size: 16pt; color: #0e285c; margin: 8mm 0 4mm; } .small { font-size: 7.5pt; color: #444444; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; } th { background: #eef2f7; } th, td { padding: 1.4mm 1.6mm; border-bottom: 0.2mm solid #cccccc; vertical-align: top; text-align: start; } .plain td { border: 0; } .num { text-align: end; white-space: nowrap; }
</style>
<p class="small">{{ $i['seller']['name'] }}, {{ $i['seller']['street'] }}, {{ trim($i['seller']['postal_code'].' '.$i['seller']['city']) }}, {{ $i['seller']['country'] }}</p>
<p>{{ $i['buyer']['name'] }}<br>{{ $i['buyer']['address']['line1'] }}<br>@if ($i['buyer']['address']['line2']){{ $i['buyer']['address']['line2'] }}<br>@endif{{ trim($i['buyer']['address']['postal_code'].' '.$i['buyer']['address']['city']) }}<br>{{ $i['buyer']['address']['country_code'] }}@if ($i['buyer']['tax']['tax_number'])<br>{{ $t('invoices:document.tax_number') }}: {{ $i['buyer']['tax']['tax_number'] }}@endif @if ($i['buyer']['tax']['vat_id'])<br>{{ $t('invoices:document.vat_id') }}: {{ $i['buyer']['tax']['vat_id'] }}@endif</p>
<h1>{{ $t('invoices:document.'.$i['kind']) }} {{ $i['number'] }}</h1>
<table class="plain">
    <tr><td>{{ $t('invoices:document.issue_date') }}: {{ $format->day($i['issue_date']) }}</td><td>{{ $t('invoices:document.service_period') }}: {{ $format->day($i['service_from']) }} - {{ $format->day($i['service_to']) }}</td><td>{{ $t('invoices:document.customer_number') }}: {{ $i['buyer']['number'] }}</td></tr>
    @isset($i['cancels'])<tr><td colspan="3">{{ $t($i['kind'] === 'credit_note' ? 'invoices:document.credits' : 'invoices:document.cancels', ['number' => $i['cancels']]) }} ({{ $format->day($i['cancels_date']) }})</td></tr>@endisset
</table>
<table>
    <thead><tr><th>{{ $t('invoices:document.position') }}</th><th>{{ $t('invoices:document.description') }}</th><th class="num">{{ $t('invoices:document.quantity') }}</th><th class="num">{{ $t('invoices:document.unit_price') }}</th><th class="num">{{ $t('invoices:document.tax_rate') }}</th><th class="num">{{ $t('invoices:document.net') }}</th></tr></thead>
    <tbody>
    @foreach ($i['lines'] as $line)
        <tr><td>{{ $line['position'] }}</td><td>{{ $line['description'] }}</td><td class="num">{{ $line['minutes'] !== null ? $hours($line['minutes']) : $decimal($line['quantity'], 0) }} {{ $t('invoices:unit.'.$line['unit']) }}</td><td class="num">{{ $decimal($line['unit_price']) }}</td><td class="num">{{ $decimal(\App\Domain\Money\Amount::decimal(intdiv($line['rate_ppm'], 100), 'EUR'), 0) }} %</td><td class="num">{{ $format->money($line['net_minor'], $i['currency']) }}</td></tr>
    @endforeach
    </tbody>
</table>
<table class="plain">
    <tr><td></td><td class="num">{{ $t('invoices:document.net_total') }}</td><td class="num">{{ $format->money($i['net_minor'], $i['currency']) }}</td></tr>
    @foreach ($i['groups'] as $group)
        <tr><td></td><td class="num">{{ $t('invoices:document.tax_on', ['rate' => $decimal(\App\Domain\Money\Amount::decimal(intdiv($group['rate_ppm'], 100), 'EUR'), 0), 'net' => $format->money($group['net_minor'], $i['currency'])]) }}</td><td class="num">{{ $format->money($group['tax_minor'], $i['currency']) }}</td></tr>
    @endforeach
    <tr><td></td><td class="num"><strong>{{ $i['kind'] === 'credit_note' ? $t('invoices:document.credited_total') : ($i['kind'] === 'cancellation' ? $t('invoices:document.cancelled_total') : $t('invoices:document.gross_total')) }}</strong></td><td class="num"><strong>{{ $format->money($i['gross_minor'], $i['currency']) }}</strong></td></tr>
</table>
@foreach ($i['notices'] as $notice)<p>{{ $notice }}</p>@endforeach
@isset($i['reason'])<p>{{ $i['reason'] }}</p>@endisset
@if ($i['kind'] === 'invoice')<p>{{ $t('invoices:document.pay_until', ['date' => $format->day($i['due_date'])]) }}</p>@endif
<p class="small">
    @if ($i['seller']['tax_number'] !== ''){{ $t('invoices:document.tax_number') }}: {{ $i['seller']['tax_number'] }} @endif
    @if ($i['seller']['vat_id'] !== ''){{ $t('invoices:document.vat_id') }}: {{ $i['seller']['vat_id'] }} @endif
    <br>{{ trim($i['seller']['bank'].' '.$i['seller']['iban'].' '.$i['seller']['bic']) }}<br>{{ $i['seller']['contact'] }}
</p>

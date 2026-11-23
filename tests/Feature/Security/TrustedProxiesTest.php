<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use Illuminate\Testing\TestResponse;

/*
 * S-0923: forwarded headers count only from the configured proxy
 * (TRUSTED_PROXIES), so a client cannot claim another address or HTTPS.
 */

function forwardedRequest(string $from): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $from])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.7'])
        ->get('/health');
}

it('believes the configured proxy', function () {
    config(['trustedproxy.proxies' => '172.31.240.10', 'health.allowed_ips' => '198.51.100.7']);

    forwardedRequest('172.31.240.10')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security');
});

it('ignores forwarded headers from anyone else', function () {
    config(['trustedproxy.proxies' => '172.31.240.10', 'health.allowed_ips' => '198.51.100.7']);

    forwardedRequest('203.0.113.9')->assertNotFound()->assertHeaderMissing('Strict-Transport-Security');
});

it('trusts no proxy by default', function () {
    expect(config('trustedproxy.proxies'))->toBeNull();

    config(['health.allowed_ips' => '198.51.100.7']);

    forwardedRequest('172.31.240.10')->assertNotFound();
});

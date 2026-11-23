<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * S-0923: forwarded headers count only from the configured proxy
 * (TRUSTED_PROXIES), so a client cannot claim another address or HTTPS.
 */

it('believes the configured proxy', function () {
    config(['trustedproxy.proxies' => '172.31.240.10', 'health.allowed_ips' => '198.51.100.7']);

    $this->withServerVariables(['REMOTE_ADDR' => '172.31.240.10'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.7'])
        ->get('/health')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security');
});

it('ignores forwarded headers from anyone else', function () {
    config(['trustedproxy.proxies' => '172.31.240.10', 'health.allowed_ips' => '198.51.100.7']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.7'])
        ->get('/health')
        ->assertNotFound()
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('trusts no proxy by default', function () {
    expect(config('trustedproxy.proxies'))->toBeNull();

    config(['health.allowed_ips' => '198.51.100.7']);

    $this->withServerVariables(['REMOTE_ADDR' => '172.31.240.10'])
        ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
        ->get('/health')
        ->assertNotFound();
});

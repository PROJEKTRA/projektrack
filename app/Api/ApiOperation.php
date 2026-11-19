<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Api;

use Attribute;

/**
 * Describes one /api/v1 operation for the OpenAPI document (S-0913).
 * `php artisan projektrack:api:openapi` reads it from every API route; CI
 * fails when a route has none or the committed document is out of date.
 * Schemas are JSON Schema (OpenAPI 3.1) as PHP arrays.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiOperation
{
    /**
     * @param  string  $summary  English, for integration developers (not user-facing text)
     * @param  string  $scope  token scope the route requires
     * @param  string|null  $permission  user permission the route requires, if any
     * @param  array<string, mixed>  $response  schema of a successful response body
     * @param  array<string, mixed>|null  $request  schema of the request body
     */
    public function __construct(
        public string $summary,
        public string $scope,
        public ?string $permission = null,
        public array $response = ['type' => 'object'],
        public int $status = 200,
        public ?array $request = null,
        public string $requestType = 'application/json',
        public string $tag = 'core',
    ) {}
}

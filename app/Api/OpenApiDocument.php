<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Api;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionMethod;

/**
 * Builds the OpenAPI 3.1 document of /api/v1 from the routes and their
 * #[ApiOperation] attributes (S-0913). Deterministic, so CI can compare it
 * with the committed docs/api/openapi.json.
 */
final class OpenApiDocument
{
    public const string PATH = 'docs/api/openapi.json';

    public function __construct(private readonly Router $router) {}

    /** @return list<string> "METHOD uri" of API routes without #[ApiOperation] */
    public function undocumented(): array
    {
        $missing = [];

        foreach ($this->apiRoutes() as $route) {
            if ($this->operationOf($route) === null) {
                $missing[] = implode('|', $this->methods($route)).' '.$route->uri();
            }
        }

        return $missing;
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $paths = [];

        foreach ($this->apiRoutes() as $route) {
            $operation = $this->operationOf($route);
            if ($operation === null) {
                continue;
            }

            $path = '/'.$route->uri();
            foreach ($this->methods($route) as $method) {
                $paths[$path][strtolower($method)] = $this->operation($route, $operation, $method);
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => config()->string('brand.name').' REST API',
                'version' => 'v1',
                'description' => 'Personal access tokens with scopes (Bearer). Every request needs the scope of the operation and the permissions of the token user, as in the user interface, inside the tenant of the host.',
            ],
            'servers' => [['url' => '/']],
            'security' => [['token' => []]],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'token' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Personal access token from Settings > API tokens.'],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'required' => ['message'],
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function json(): string
    {
        return json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /** @return list<Route> */
    private function apiRoutes(): array
    {
        $routes = array_values(array_filter(
            $this->router->getRoutes()->getRoutes(),
            fn (Route $route): bool => str_starts_with($route->uri(), 'api/'),
        ));

        usort($routes, fn (Route $a, Route $b): int => strcmp($a->uri(), $b->uri()));

        return $routes;
    }

    /** @return list<string> */
    private function methods(Route $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD']));
    }

    private function operationOf(Route $route): ?ApiOperation
    {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        $attributes = (new ReflectionMethod($class, $method))->getAttributes(ApiOperation::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /** @return array<string, mixed> */
    private function operation(Route $route, ApiOperation $operation, string $method): array
    {
        $error = fn (string $description): array => [
            'description' => $description,
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
        ];

        $responseType = ($operation->response['contentMediaType'] ?? null) === 'application/octet-stream'
            ? 'application/octet-stream'
            : 'application/json';

        $result = [
            'operationId' => (string) $route->getName(),
            'tags' => [$operation->tag],
            'summary' => $operation->summary,
            'x-scope' => $operation->scope,
            'responses' => [
                (string) $operation->status => [
                    'description' => 'Success',
                    'content' => [$responseType => ['schema' => $operation->response]],
                ],
                '401' => $error('No valid token.'),
                '403' => $error('The token lacks the scope, or the user the permission.'),
                '429' => $error('Too many requests for this token.'),
            ],
        ];

        if ($operation->permission !== null) {
            $result['x-permission'] = $operation->permission;
        }

        $parameters = [];
        foreach ($route->parameterNames() as $name) {
            $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
            $result['responses']['404'] = $error('Not found in the tenant of the host.');
        }
        if ($parameters !== []) {
            $result['parameters'] = $parameters;
        }

        if ($operation->request !== null && $method !== 'GET') {
            $result['requestBody'] = ['required' => true, 'content' => [$operation->requestType => ['schema' => $operation->request]]];
            $result['responses']['422'] = $error('The request is not valid.');
        }

        ksort($result['responses']);

        return $result;
    }
}

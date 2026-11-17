<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace App\Http\Middleware;

use App\Authorization\Permissions;
use App\Authorization\ProjectScoped;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "permission:<key>" or "permission:<key>,<route parameter>".
 *
 * With a route parameter, the check also counts the user's project roles in
 * the project of that parameter (a ProjectScoped model or a project id).
 */
class RequirePermission
{
    public function __construct(private readonly Permissions $permissions) {}

    public function handle(Request $request, Closure $next, string $permission, ?string $projectParameter = null): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $projectId = null;
        if ($projectParameter !== null) {
            $value = $request->route($projectParameter);
            $projectId = match (true) {
                $value instanceof ProjectScoped => $value->scopedProjectId(),
                is_string($value) => $value,
                default => null,
            };
            abort_if($projectId === null, 403);
        }

        abort_unless($this->permissions->allows($user, $permission, $projectId), 403);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

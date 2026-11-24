<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Projects\Http;

use App\Api\ApiOperation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Domain\Projects;

class ProjectApiController extends Controller
{
    private const array SCHEMA = ['type' => 'object', 'required' => ['id', 'number', 'name', 'status', 'health', 'booked_minutes']];

    #[ApiOperation(
        summary: 'Projects the token user may open, by name, 50 per page ("page").',
        scope: 'projects:read',
        response: ['type' => 'object', 'required' => ['data', 'page', 'pages'], 'properties' => ['data' => ['type' => 'array', 'items' => self::SCHEMA], 'page' => ['type' => 'integer'], 'pages' => ['type' => 'integer']]],
        tag: 'projects',
    )]
    public function index(Request $request, Projects $projects, ProjectView $view): JsonResponse
    {
        $page = $projects->visibleTo($this->currentUser($request))->orderBy('name')->orderBy('id')->paginate(50);

        return response()->json(['data' => $view->rows(array_values($page->items())), 'page' => $page->currentPage(), 'pages' => $page->lastPage()]);
    }

    #[ApiOperation(summary: 'One project with members, contracts and work packages. The money budget needs projects.budget.view.', scope: 'projects:read', response: self::SCHEMA, tag: 'projects')]
    public function show(Request $request, string $project, Projects $projects, ProjectView $view): JsonResponse
    {
        $user = $this->currentUser($request);

        return response()->json($view->details($projects->visibleTo($user)->findOrFail($project), $user));
    }
}

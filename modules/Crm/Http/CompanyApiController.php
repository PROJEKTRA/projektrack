<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Modules\Crm\Http;

use App\Api\ApiOperation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Domain\Companies;
use Modules\Crm\Domain\Company;

/**
 * Customers over the API (S-0939), with the same checks as the dialog.
 */
class CompanyApiController extends Controller
{
    private const array SCHEMA = [
        'type' => 'object',
        'required' => ['id', 'number', 'name', 'kind', 'status'],
        'properties' => [
            'id' => ['type' => 'string'],
            'number' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'legal_name' => ['type' => ['string', 'null']],
            'kind' => ['type' => 'string', 'enum' => Company::KINDS],
            'status' => ['type' => 'string', 'enum' => Company::STATUSES],
            'document_locale' => ['type' => 'string'],
            'currency' => ['type' => 'string'],
            'payment_days' => ['type' => 'integer'],
            'einvoice_format' => ['type' => 'string', 'enum' => Company::EINVOICE_FORMATS],
            'leitweg_id' => ['type' => ['string', 'null']],
            'tax' => ['type' => ['object', 'null']],
            'address' => ['type' => ['object', 'null']],
            'contacts' => ['type' => 'array', 'items' => ['type' => 'object']],
            'sites' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
    ];

    private const array INPUT = [
        'type' => 'object',
        'required' => ['name', 'kind', 'document_locale', 'currency', 'payment_days', 'einvoice_format'],
        'properties' => self::SCHEMA['properties'],
    ];

    #[ApiOperation(
        summary: 'Customers by name, 50 per page ("page", "search", "status").',
        scope: 'crm:read',
        permission: 'crm.view',
        response: [
            'type' => 'object',
            'required' => ['data', 'page', 'pages'],
            'properties' => [
                'data' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'number', 'name', 'kind', 'status']]],
                'page' => ['type' => 'integer'],
                'pages' => ['type' => 'integer'],
            ],
        ],
        tag: 'crm',
    )]
    public function index(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->limit(100, '')->toString());
        $page = CompanySearch::query($search, $request->query('status') === 'archived' ? 'archived' : 'active')->paginate(50);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Company $company): array => $company->only(['id', 'number', 'name', 'kind', 'status']))->all(),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
        ]);
    }

    #[ApiOperation(summary: 'One customer with tax profile, address, contacts and sites.', scope: 'crm:read', permission: 'crm.view', response: self::SCHEMA, tag: 'crm')]
    public function show(string $company, Companies $companies): JsonResponse
    {
        return response()->json($companies->describe(Company::query()->findOrFail($company)));
    }

    #[ApiOperation(summary: 'Create a customer with everything that belongs to it. Needs crm.tax_profile.manage, and crm.contacts.manage for contacts.', scope: 'crm:write', permission: 'crm.manage', response: self::SCHEMA, status: 201, request: self::INPUT, tag: 'crm')]
    public function store(Request $request, CompanyInput $input, Companies $companies): JsonResponse
    {
        $user = $this->currentUser($request);
        $company = $companies->create($input->validate($request->all(), $user, true), $user);

        return response()->json($companies->describe($company), 201);
    }

    #[ApiOperation(summary: 'Change a customer. Parts that are left out (tax, address, contacts, sites) stay as they are; a list that is sent replaces the stored one.', scope: 'crm:write', permission: 'crm.manage', response: self::SCHEMA, request: self::INPUT, tag: 'crm')]
    public function update(Request $request, string $company, CompanyInput $input, Companies $companies): JsonResponse
    {
        $user = $this->currentUser($request);
        $saved = $companies->update(Company::query()->findOrFail($company), $input->validate($request->all(), $user, false), $user);

        return response()->json($companies->describe($saved));
    }
}

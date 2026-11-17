<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\FieldAccess;
use App\Authorization\FieldRisk;
use App\Modules\InvalidModuleManifest;
use App\Modules\ModuleManifest;

/*
 * Permission-related parts of module.json (modules.md, section 2).
 */

function timesheetsManifest(array $overrides = []): array
{
    return [
        'key' => 'timesheets', 'name_string' => 'timesheets:module_name', 'version' => '0.9.0', 'core' => '^0.9',
        'edition' => 'community', 'requires' => ['core'],
        'permissions' => ['timesheets.view', 'timesheets.view_rates'],
        ...$overrides,
    ];
}

it('reads field permissions and role defaults', function () {
    $manifest = ModuleManifest::fromArray(timesheetsManifest([
        'field_permissions' => [
            ['entity' => 'time_entries', 'field' => 'sales_amount', 'default' => 'hidden', 'risk' => 'financial', 'permission' => 'timesheets.view_rates'],
        ],
        'role_defaults' => ['employee' => ['timesheets.view']],
        'project_role_defaults' => ['project_lead' => ['timesheets.view_rates']],
    ]), '/tmp');

    $field = $manifest->fieldPermissions[0];

    expect($field->entity)->toBe('time_entries')
        ->and($field->default)->toBe(FieldAccess::Hidden)
        ->and($field->risk)->toBe(FieldRisk::Financial)
        ->and($field->permission)->toBe('timesheets.view_rates')
        ->and($manifest->roleDefaults)->toBe(['employee' => ['timesheets.view']])
        ->and($manifest->projectRoleDefaults)->toBe(['project_lead' => ['timesheets.view_rates']]);
});

it('rejects invalid permission declarations', function (array $overrides, string $problem) {
    expect(fn () => ModuleManifest::fromArray(timesheetsManifest($overrides), '/tmp'))
        ->toThrow(InvalidModuleManifest::class, $problem);
})->with([
    'foreign namespace' => [['permissions' => ['invoices.finalize']], 'must start with the module key'],
    'visible financial field' => [
        ['field_permissions' => [['entity' => 'time_entries', 'field' => 'cost_amount', 'default' => 'read', 'risk' => 'financial']]],
        'must default to hidden',
    ],
    'field gated by an undeclared permission' => [
        ['field_permissions' => [['entity' => 'time_entries', 'field' => 'cost_amount', 'default' => 'hidden', 'permission' => 'timesheets.other']]],
        'entry 0 is invalid',
    ],
    'default grant of an undeclared permission' => [['role_defaults' => ['employee' => ['timesheets.approve']]], 'does not declare'],
]);

it('lets only the invoices module use the tax namespace', function () {
    $manifest = ModuleManifest::fromArray([
        ...timesheetsManifest(), 'key' => 'invoices', 'name_string' => 'invoices:module_name',
        'permissions' => ['invoices.finalize', 'tax.legal_notice.override'],
    ], '/tmp');

    expect($manifest->permissions)->toContain('tax.legal_notice.override')
        ->and(fn () => ModuleManifest::fromArray(timesheetsManifest(['permissions' => ['tax.legal_notice.override']]), '/tmp'))
        ->toThrow(InvalidModuleManifest::class);
});

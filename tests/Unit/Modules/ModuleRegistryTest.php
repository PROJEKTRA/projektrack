<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Modules\Edition;
use App\Modules\ModuleConflict;
use App\Modules\ModuleRegistry;
use App\Modules\UnknownModule;
use Psr\Log\AbstractLogger;

/*
 * S-0901: module manifests are loaded, validated and ordered by dependency.
 */

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{string, array<mixed>}> */
    public array $records = [];

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->records[] = [(string) $message, $context];
    }
}

/**
 * @param  array<string, array<string, mixed>|string>  $modules  folder => manifest (array) or raw file content (string)
 */
function moduleFixture(array $modules): string
{
    $root = sys_get_temp_dir().'/ptk-modules-'.bin2hex(random_bytes(6));
    mkdir($root);

    foreach ($modules as $folder => $manifest) {
        mkdir("{$root}/{$folder}");
        file_put_contents(
            "{$root}/{$folder}/module.json",
            is_string($manifest) ? $manifest : json_encode($manifest, JSON_PRETTY_PRINT),
        );
    }

    return $root;
}

/**
 * @param  list<string>  $requires
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function manifest(string $key, array $requires = ['core'], array $extra = []): array
{
    return array_merge([
        'key' => $key,
        'name_string' => "{$key}:module_name",
        'version' => '0.9.0',
        'core' => '^0.9',
        'edition' => 'community',
        'requires' => $requires,
        'permissions' => ["{$key}.view"],
    ], $extra);
}

function registry(string $root, ?RecordingLogger $logger = null): ModuleRegistry
{
    return new ModuleRegistry([$root], $logger ?? new RecordingLogger);
}

it('loads a valid module with name, version, edition and requirements', function () {
    $registry = registry(moduleFixture([
        'Projects' => manifest('projects'),
        'Timesheets' => manifest('timesheets', ['core', 'projects'], ['edition' => 'community', 'version' => '1.2.3']),
    ]));

    $timesheets = $registry->get('timesheets');

    expect($timesheets->nameString)->toBe('timesheets:module_name')
        ->and($timesheets->version)->toBe('1.2.3')
        ->and($timesheets->edition)->toBe(Edition::Community)
        ->and($timesheets->requires)->toBe(['projects'])
        ->and($registry->rejected())->toBe([]);
});

it('orders modules so every module boots after the modules it requires', function () {
    $registry = registry(moduleFixture([
        'A' => manifest('service_reports', ['core', 'timesheets', 'projects']),
        'B' => manifest('timesheets', ['core', 'projects', 'rates']),
        'C' => manifest('rates', ['core', 'projects']),
        'D' => manifest('projects', ['core', 'crm']),
        'E' => manifest('crm'),
    ]));

    expect(array_keys($registry->all()))
        ->toBe(['crm', 'projects', 'rates', 'timesheets', 'service_reports']);
});

it('rejects a manifest without a key and logs the reason', function () {
    $logger = new RecordingLogger;
    $data = manifest('projects');
    unset($data['key']);

    $registry = registry(moduleFixture(['Broken' => $data]), $logger);

    expect($registry->all())->toBe([])
        ->and(array_values($registry->rejected())[0])->toContain('"key" is missing')
        ->and($logger->records[0][0])->toBe('Module rejected');
});

it('rejects a module that requires an unknown module, and everything that needs it', function () {
    $registry = registry(moduleFixture([
        'Timesheets' => manifest('timesheets', ['core', 'nonexistent']),
        'ServiceReports' => manifest('service_reports', ['core', 'timesheets']),
        'Crm' => manifest('crm'),
    ]));

    expect(array_keys($registry->all()))->toBe(['crm'])
        ->and($registry->rejected())->toHaveCount(2);
});

it('rejects modules that form a dependency cycle', function () {
    $registry = registry(moduleFixture([
        'A' => manifest('alpha', ['core', 'beta']),
        'B' => manifest('beta', ['core', 'alpha']),
        'C' => manifest('crm'),
    ]));

    expect(array_keys($registry->all()))->toBe(['crm'])
        ->and(implode(' ', array_merge(...array_values($registry->rejected()))))->toContain('cycle');
});

it('rejects invalid manifests with readable reasons', function (array|string $content, string $reason) {
    $registry = registry(moduleFixture(['X' => $content]));

    expect(implode(' ', array_values($registry->rejected())[0] ?? []))->toContain($reason);
})->with([
    'not json' => ['{ not json', 'not a JSON object'],
    'bad key' => [manifest('Bad-Key'), '"key" has an invalid format'],
    'core as key' => [manifest('core'), 'must not be "core"'],
    'unknown edition' => [manifest('crm', ['core'], ['edition' => 'gold']), '"edition" must be one of'],
    'bad version' => [manifest('crm', ['core'], ['version' => '1.0']), '"version" has an invalid format'],
    'requires not a list' => [manifest('crm', [], ['requires' => 'core']), '"requires" must be a list'],
    'self dependency' => [manifest('crm', ['core', 'crm']), 'depend on or conflict with itself'],
    'bad permission' => [manifest('crm', ['core'], ['permissions' => ['CRM view']]), '"permissions" contains an invalid entry'],
]);

it('rejects a second module with the same key', function () {
    $registry = registry(moduleFixture([
        'Crm' => manifest('crm'),
        'CrmCopy' => manifest('crm'),
    ]));

    expect($registry->all())->toHaveCount(1)
        ->and(implode(' ', array_values($registry->rejected())[0]))->toContain('already used');
});

it('plans to enable missing dependencies first, in boot order', function () {
    $registry = registry(moduleFixture([
        'Crm' => manifest('crm'),
        'Projects' => manifest('projects', ['core', 'crm']),
        'Rates' => manifest('rates', ['core', 'crm', 'projects']),
        'Timesheets' => manifest('timesheets', ['core', 'projects', 'rates']),
        'ServiceReports' => manifest('service_reports', ['core', 'projects', 'timesheets']),
    ]));

    expect($registry->enablePlan('service_reports', ['crm', 'projects']))
        ->toBe(['rates', 'timesheets', 'service_reports'])
        ->and($registry->enablePlan('crm', ['crm']))->toBe([]);
});

it('refuses to enable modules that conflict', function () {
    $registry = registry(moduleFixture([
        'Old' => manifest('legacy_billing'),
        'New' => manifest('invoices', ['core'], ['conflicts' => ['legacy_billing']]),
    ]));

    $registry->enablePlan('invoices', ['legacy_billing']);
})->throws(ModuleConflict::class);

it('lists enabled modules that still depend on a module before it can be disabled', function () {
    $registry = registry(moduleFixture([
        'Crm' => manifest('crm'),
        'Projects' => manifest('projects', ['core', 'crm']),
        'Timesheets' => manifest('timesheets', ['core', 'projects']),
        'Absences' => manifest('absences'),
    ]));

    expect($registry->dependentsOf('crm', ['crm', 'projects', 'timesheets', 'absences']))
        ->toBe(['projects', 'timesheets'])
        ->and($registry->dependentsOf('timesheets', ['crm', 'projects', 'timesheets']))->toBe([]);
});

it('fails loudly for an unknown module key', function () {
    registry(moduleFixture([]))->get('nonexistent');
})->throws(UnknownModule::class);

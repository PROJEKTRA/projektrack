<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Api\ApiTokens;
use App\Authorization\RoleManager;
use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Settings\Settings;
use App\Tenancy\TenantContext;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Modules\Crm\Domain\Companies;
use Modules\Crm\Domain\Company;
use Modules\Crm\Domain\Contact;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceDelivery;
use Modules\Invoices\Domain\InvoiceLine;
use Modules\Invoices\Domain\InvoicePayment;
use Modules\Invoices\Domain\Invoices;
use Modules\Projects\Domain\Contract;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\ProjectMember;
use Modules\Projects\Domain\WorkBreakdown;
use Modules\Projects\Domain\WorkPackage;
use Modules\Rates\Domain\JobRole;
use Modules\Rates\Domain\RateRule;
use Modules\Rates\Domain\Rates;
use Modules\Rates\Domain\Valuation;
use Modules\ServiceReports\Domain\Reports;
use Modules\ServiceReports\Domain\ServiceReport;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\Timer;
use Modules\Timesheets\Domain\Timesheets;

/*
 * S-0902, S-0906: every module route over HTTP, from a second tenant with the ids
 * of the first, and from users without the right. Routes are read from the router,
 * so a new one fails here until it is covered.
 */

beforeEach(function () {
    $this->travelTo('2026-11-05 09:00:00');
    $this->tenant->update(['country_code' => 'DE', 'base_currency' => 'EUR', 'timezone' => 'Europe/Berlin']);
    app(TenantModules::class)->enable('invoices');
    $this->home = $this->tenant;
    $this->ids = xtWorld();
    $this->before = xtState();
    $this->other = Tenant::factory()->withDomain('other.test')->create();
    [$this->intruder, $this->foreignProject] = app(TenantContext::class)->run($this->other, function () {
        app(TenantModules::class)->enable('invoices');
        app()->forgetScopedInstances();
        $intruder = xtUser('admin');
        $company = Company::query()->create(['number' => 'C-00001', 'name' => 'Intruder customer', 'kind' => 'customer', 'status' => 'active', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'pdf']);
        $project = Project::query()->create(['number' => 'P-00001', 'name' => 'Intruder project', 'company_id' => $company->id, 'status' => 'active', 'billing_model' => 'time_and_material', 'currency' => 'EUR']);

        return [$intruder, $project];
    });
    app()->forgetScopedInstances();
    $this->withSession(['auth.password_confirmed_at' => now()->getTimestamp()]);
});

function xtUser(string $role): User
{
    $user = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($user, Role::query()->where('key', $role)->firstOrFail());

    return $user;
}

/** @return array<string, string> route parameter => id of the first tenant */
function xtWorld(): array
{
    $admin = xtUser('admin');
    app(Settings::class)->save('invoices', ['legal_name' => 'Synthetic Seller GmbH', 'street' => 'Teststr. 1', 'postal_code' => '70173', 'city' => 'Stuttgart', 'tax_number' => '99/999/99999', 'vat_id' => 'DE999999999', 'vat_status' => 'registered', 'bank' => 'Test Bank', 'iban' => 'DE00 0000', 'bic' => 'TESTDEXX', 'contact' => 'billing@example.test', 'contact_name' => 'Test Contact', 'contact_phone' => '+49 000 0000', 'contact_email' => 'billing@example.test', 'legal_texts_confirmed' => true], $admin);
    app()->forgetScopedInstances();
    $company = app(Companies::class)->create([
        'company' => ['name' => 'Victim customer', 'kind' => 'customer', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'zugferd'],
        'tax' => ['tax_status' => 'domestic', 'country_code' => 'DE'],
        'address' => ['line1' => 'Weg 2', 'city' => 'Ort', 'postal_code' => '10000', 'country_code' => 'DE'],
        'contacts' => [['last_name' => 'Billing', 'email' => 'billing@customer.test', 'invoice_recipient' => true]],
    ], $admin);
    $project = Project::query()->create(['number' => 'P-00001', 'name' => 'Victim project', 'company_id' => $company->id, 'status' => 'active', 'billing_model' => 'time_and_material', 'currency' => 'EUR']);
    $contract = Contract::query()->create(['project_id' => $project->id, 'number' => 'SOW-1', 'contract_type' => 'time_and_material', 'status' => 'active', 'rounding_minutes' => 1, 'minimum_minutes' => 0]);
    $package = app(WorkBreakdown::class)->add($project, ['name' => 'Consulting', 'billable' => true, 'contract_id' => $contract->id], null);
    $worker = xtUser('employee');
    $member = ProjectMember::query()->create(['project_id' => $project->id, 'user_id' => $worker->id, 'project_role_id' => ProjectRole::query()->where('key', 'project_member')->firstOrFail()->id]);
    app()->forgetScopedInstances();
    $rule = app(Rates::class)->add('tenant', null, '100', 'EUR', '2026-01-01', $admin);
    $jobRole = JobRole::query()->create(['name' => 'Junior Consultant']);
    $approved = app(Timesheets::class)->book($worker, $package->id, '2026-10-05', 60, 'Analysis');
    app(Timesheets::class)->submitWeek($worker, '2026-10-05');
    app(Timesheets::class)->approve($admin, [$approved->id]);
    $submitted = app(Timesheets::class)->book($worker, $package->id, '2026-10-13', 30, 'Waiting');
    app(Timesheets::class)->submitWeek($worker, '2026-10-13');
    $report = app(Reports::class)->generate($project->id, '2026-10-01', '2026-10-31', true, $admin);
    $line = ['description' => 'Work', 'quantity' => '1', 'unit' => 'piece', 'unit_price' => '100', 'rate_class' => 'standard'];
    $draft = app(Invoices::class)->draft($company->id, '2026-10-01', '2026-10-31', [$line], $admin);
    $issued = app(Invoices::class)->finalize(app(Invoices::class)->draft($company->id, '2026-10-01', '2026-10-31', [$line], $admin), $admin);
    app()->forgetScopedInstances();

    return [
        'company' => $company->id, 'project' => $project->id, 'contract' => $contract->id, 'package' => $package->id, 'member' => $member->id, 'rule' => $rule->id,
        'entry' => $approved->id, 'report' => $report->id, 'invoice' => $issued->id,
        // Not in addresses.
        'draft' => $draft->id, 'submitted' => $submitted->id, 'worker' => $worker->id, 'job_role' => $jobRole->id,
    ];
}

/** @return array<string, string> everything of the first tenant, as stored */
function xtState(): array
{
    return app(TenantContext::class)->run(test()->home, function (): array {
        $state = [];
        foreach ([Company::class, Contact::class, Project::class, ProjectMember::class, Contract::class, WorkPackage::class, TimeEntry::class, Timer::class, RateRule::class, JobRole::class, Valuation::class, ServiceReport::class, Invoice::class, InvoiceLine::class, InvoicePayment::class, InvoiceDelivery::class] as $model) {
            $state[$model] = $model::query()->orderBy('id')->get()->toJson();
        }

        return $state;
    });
}

/** @return array<string, mixed> input that passes validation, so a refusal is about the right and not about the input */
function xtPayload(string $name): array
{
    $company = ['name' => 'Taken over', 'kind' => 'customer', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 1, 'einvoice_format' => 'pdf'];

    return [
        'crm.companies.update' => $company,
        'api.v1.crm.companies.update' => $company,
        'projects.update' => ['name' => 'Taken over', 'company_id' => test()->ids['company'], 'billing_model' => 'internal', 'currency' => 'EUR'],
        'projects.members.store' => ['user_id' => test()->intruder->id, 'project_role_id' => test()->intruder->id],
        'projects.contracts.store' => ['number' => 'X', 'contract_type' => 'retainer', 'status' => 'active', 'rounding_minutes' => 1, 'minimum_minutes' => 0],
        'projects.contracts.update' => ['number' => 'X', 'contract_type' => 'retainer', 'status' => 'closed', 'rounding_minutes' => 60, 'minimum_minutes' => 0],
        'projects.wbs.store' => ['name' => 'X', 'billable' => true],
        'projects.wbs.update' => ['name' => 'X', 'billable' => false],
        'projects.wbs.move' => ['parent_id' => null, 'place' => 0],
        'rates.end' => ['valid_to' => '2026-12-31'],
        'timesheets.correct' => ['minutes' => 0, 'reason' => 'x'],
        'timesheets.approvals.decide' => ['ids' => [test()->ids['submitted']], 'decision' => 'approve'],
        'service_reports.store' => ['project_id' => test()->ids['project'], 'period_from' => '2026-10-01', 'period_to' => '2026-10-31'],
        'invoices.update' => ['service_from' => '2026-10-01', 'service_to' => '2026-10-31', 'lines' => []],
        'invoices.finalize' => ['gross_minor' => 11900],
        'invoices.credit' => ['reason' => 'x', 'lines' => ['1' => '1']],
        'invoices.tax_case' => ['case' => 'DE_DOMESTIC_REDUCED', 'reason' => 'A reason that is long enough'],
        'invoices.pay' => ['amount' => '1', 'paid_on' => '2026-11-05'],
        'invoices.send' => ['recipients' => ['billing@customer.test']],
        'invoices.fiscal' => ['fiscal_reference' => 'FR-TEST-0001'],
    ][$name] ?? [];
}

/** @return list<array{0: string, 1: string, 2: string}> name, method, path of every module route */
function xtRoutes(bool $api, bool $withIdsOnly): array
{
    $routes = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var RouteDefinition $route */
        $module = collect($route->gatherMiddleware())->first(fn (mixed $m): bool => is_string($m) && str_starts_with($m, 'module:'));
        // Licensing runs on the instance of the licensor only and cannot be switched on elsewhere.
        if ($module === null || $module === 'module:licensing' || str_starts_with($route->uri(), 'api/') !== $api || ($withIdsOnly && $route->parameterNames() === [])) {
            continue;
        }
        $path = $route->uri();
        foreach ($route->parameterNames() as $parameter) {
            expect(test()->ids)->toHaveKey($parameter);
            $path = str_replace('{'.$parameter.'}', test()->ids[$parameter], $path);
        }
        $routes[] = [(string) $route->getName(), array_values(array_diff($route->methods(), ['HEAD']))[0], $path];
    }
    expect($routes)->not->toBe([]);

    return $routes;
}

it('answers every web address with an id of another tenant as missing and changes nothing', function () {
    $this->actingAs($this->intruder);
    // The same user works in the own tenant: the refusals below are about the ids.
    $this->get('http://other.test/invoices')->assertOk();
    $this->get("http://other.test/projects/{$this->foreignProject->id}")->assertOk();

    $wrong = [];
    foreach (xtRoutes(false, true) as [$name, $method, $path]) {
        foreach ($name !== '' && str_starts_with($name, 'invoices.') ? [$path, str_replace($this->ids['invoice'], $this->ids['draft'], $path)] : [$path] as $address) {
            $status = $this->call($method, "http://other.test/{$address}", xtPayload($name))->getStatusCode();
            if ($status !== 404) {
                $wrong[] = "{$method} {$name}: {$status}";
            }
        }
    }

    expect($wrong)->toBe([])->and(xtState())->toBe($this->before);
});

it('answers every API address with an id of another tenant as missing and changes nothing', function () {
    $secret = app(TenantContext::class)->run($this->other, fn (): string => app(ApiTokens::class)->create($this->intruder, 't', ['crm:read', 'crm:write', 'projects:read', 'timesheets:read', 'timesheets:write'], 30)['secret']);
    $this->withToken($secret)->getJson('http://other.test/api/v1/projects')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->foreignProject->id);
    $this->getJson('http://other.test/api/v1/crm/companies')->assertOk()->assertJsonCount(1, 'data');

    $wrong = [];
    foreach (xtRoutes(true, true) as [$name, $method, $path]) {
        $status = $this->json($method, "http://other.test/{$path}", xtPayload($name))->getStatusCode();
        if ($status !== 404) {
            $wrong[] = "{$method} {$name}: {$status}";
        }
    }
    expect($wrong)->toBe([]);
    $this->putJson('http://other.test/api/v1/timesheets/entries', ['work_package_id' => $this->ids['package'], 'date' => '2026-11-03', 'minutes' => 60])->assertUnprocessable();
    $this->getJson('http://other.test/api/v1/timesheets/entries?from=2026-10-01&to=2026-10-31')->assertOk()->assertJsonCount(0, 'data');
    expect(xtState())->toBe($this->before);
});

it('never accepts the token of one tenant at another', function () {
    $secret = app(TenantContext::class)->run($this->other, fn (): string => app(ApiTokens::class)->create($this->intruder, 't', ['projects:read'], 30)['secret']);

    $this->withToken($secret)->getJson('http://localhost/api/v1/projects')->assertUnauthorized();
});

it('refuses ids of another tenant inside the input', function () {
    $this->actingAs($this->intruder);
    $at = fn (string $path): string => "http://other.test/{$path}";
    $project = "projects/{$this->foreignProject->id}";
    $ids = $this->ids;
    $period = ['project_id' => $ids['project'], 'period_from' => '2026-10-01', 'period_to' => '2026-10-31'];
    $memberRole = app(TenantContext::class)->run($this->other, fn (): string => ProjectRole::query()->where('key', 'project_member')->firstOrFail()->id);

    $this->post($at('projects'), ['name' => 'x', 'company_id' => $ids['company'], 'billing_model' => 'internal', 'currency' => 'EUR'])->assertSessionHasErrors('company_id');
    $this->post($at("{$project}/members"), ['user_id' => $ids['worker'], 'project_role_id' => $memberRole])->assertNotFound();
    $this->post($at("{$project}/work-packages"), ['name' => 'x', 'billable' => true, 'parent_id' => $ids['package']])->assertNotFound();
    $this->post($at("{$project}/work-packages"), ['name' => 'x', 'billable' => true, 'contract_id' => $ids['contract']])->assertSessionHasErrors('contract_id');
    $this->put($at("{$project}/contracts/{$ids['contract']}"), xtPayload('projects.contracts.update'))->assertNotFound();
    $this->delete($at("{$project}/members/{$ids['member']}"))->assertNotFound();
    $this->putJson($at('timesheets/cells'), ['work_package_id' => $ids['package'], 'date' => '2026-11-03', 'minutes' => 60])->assertUnprocessable()->assertJsonValidationErrors('work_package_id');
    $this->putJson($at('timesheets/cells'), ['work_package_id' => $ids['package'], 'date' => '2026-10-13', 'minutes' => 0]);
    $this->post($at('timesheets/timer'), ['work_package_id' => $ids['package']])->assertSessionHasErrors('work_package_id');
    $this->post($at('timesheets/approvals'), ['ids' => [$ids['submitted']], 'decision' => 'approve'])->assertSessionHasNoErrors();
    $this->post($at('timesheets/approvals'), ['ids' => [$ids['submitted']], 'decision' => 'reject', 'reason' => 'x'])->assertSessionHasNoErrors();
    foreach (['project' => 'project', 'company' => 'company', 'user' => 'worker', 'job_role' => 'job_role'] as $scope => $key) {
        $this->post($at('rates'), ['scope' => $scope, 'scope_id' => $ids[$key], 'rate' => '1', 'currency' => 'EUR', 'valid_from' => '2026-01-01'])->assertSessionHasErrors('scope_id');
    }
    $this->post($at('rates/job-roles/assign'), ['user_id' => $ids['worker'], 'job_role_id' => $ids['job_role'], 'valid_from' => '2026-01-01'])->assertNotFound();
    $this->post($at('rates/margin/value'), ['from' => '2026-10-01', 'to' => '2026-10-31'])->assertSessionHasNoErrors();
    $this->post($at('service-reports'), $period)->assertNotFound();
    $this->post($at('invoices'), ['company_id' => $ids['company'], 'service_from' => '2026-10-01', 'service_to' => '2026-10-31'])->assertNotFound();
    $this->post($at('invoices/from-time'), $period)->assertNotFound();
    $this->put($at("admin/users/{$ids['worker']}"), ['roles' => []])->assertNotFound();

    expect(xtState())->toBe($this->before);
    app(TenantContext::class)->run($this->other, function () {
        expect(Invoice::query()->count())->toBe(0)->and(ServiceReport::query()->count())->toBe(0)->and(RateRule::query()->count())->toBe(0)->and(TimeEntry::query()->count())->toBe(0)->and(ProjectMember::query()->count())->toBe(0);
    });
});

it('lists nothing of another tenant', function (string $path) {
    $page = (string) $this->actingAs($this->intruder)->get("http://other.test/{$path}")->assertOk()->getContent();

    foreach ($this->ids as $key => $id) {
        expect(str_contains($page, $id))->toBeFalse("{$path} shows {$key}");
    }
    expect($page)->not->toContain('Victim');
})->with(['crm/companies', 'projects', 'timesheets?date=2026-10-05', 'timesheets/approvals', 'timesheets/periods?month=2026-10', 'rates', 'rates/margin?from=2026-10-01&to=2026-10-31', 'service-reports', 'invoices', 'invoices?show=open', 'admin/users', 'dashboard']);

it('refuses every module route a role does not hold and changes nothing', function (?string $role, array $allowed) {
    $user = $role === null ? User::factory()->withTwoFactor()->create(['locale' => 'en']) : xtUser($role);
    app()->forgetScopedInstances();
    $this->actingAs($user);

    $wrong = [];
    foreach (xtRoutes(false, false) as [$name, $method, $path]) {
        if (in_array($name, $allowed, true)) {
            continue;
        }
        $status = $this->call($method, "http://localhost/{$path}", xtPayload($name))->getStatusCode();
        // Hidden reports look like missing ones.
        $expected = str_starts_with($name, 'service_reports.') && str_contains($path, $this->ids['report']) ? 404 : 403;
        if ($status !== $expected) {
            $wrong[] = "{$method} {$name}: {$status}";
        }
    }

    expect($wrong)->toBe([])->and(xtState())->toBe($this->before);
})->with([
    'no role' => [null, ['projects.index']],
    'employee' => ['employee', ['projects.index', 'timesheets.index', 'timesheets.book', 'timesheets.timer.start', 'timesheets.timer.stop', 'timesheets.timer.discard', 'timesheets.submit']],
    'external' => ['external', ['projects.index', 'timesheets.index', 'timesheets.book', 'timesheets.timer.start', 'timesheets.timer.stop', 'timesheets.timer.discard', 'timesheets.submit']],
    'project lead of no project' => ['project_lead', ['projects.index', 'projects.store', 'crm.companies.index', 'crm.companies.show', 'timesheets.index', 'timesheets.book', 'timesheets.timer.start', 'timesheets.timer.stop', 'timesheets.timer.discard', 'timesheets.submit']],
]);

<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Api\ApiTokens;
use App\Authorization\RoleManager;
use App\Models\AuditEntry;
use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Modules\Crm\Domain\Company;
use Modules\Projects\Contracts\BookedTime;
use Modules\Projects\Contracts\ProjectDirectory;
use Modules\Projects\Domain\Contract;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\ProjectMember;
use Modules\Projects\Domain\WorkBreakdown;
use Modules\Projects\Domain\WorkPackage;

/*
 * S-0940 projects and team, S-0941 contracts (rounding), S-0942 work packages.
 */

beforeEach(function () {
    app(TenantModules::class)->enable('projects');
    $this->company = projectCompany();
});

function projectCompany(): Company
{
    return Company::query()->create(['number' => 'C-00001', 'name' => 'Learnteq Demo GmbH', 'kind' => 'customer', 'status' => 'active', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'pdf']);
}

function projectUser(string $role): User
{
    $user = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($user, Role::query()->where('key', $role)->firstOrFail());

    return $user;
}

/** @param array<string, mixed> $changes */
function projectInput(array $changes = []): array
{
    return array_replace(['name' => 'GG Operations', 'company_id' => test()->company->id, 'billing_model' => 'time_and_material', 'start_date' => '2026-10-01', 'end_date' => '2027-03-31', 'budget_minutes' => 18000, 'currency' => 'EUR'], $changes);
}

function newProject(User $lead): Project
{
    test()->actingAs($lead)->post('/projects', projectInput())->assertSessionHasNoErrors();

    return Project::query()->latest('id')->firstOrFail();
}

function join_(Project $project, User $user, string $role): ProjectMember
{
    return ProjectMember::query()->create(['project_id' => $project->id, 'user_id' => $user->id, 'project_role_id' => ProjectRole::query()->where('key', $role)->firstOrFail()->id]);
}

it('creates a project that is active and green, led by its creator', function () {
    $lead = projectUser('project_lead');
    $project = newProject($lead);

    expect($project->number)->toBe('P-00001')->and($project->status)->toBe('active')->and($project->health(0))->toBe('green')
        ->and(AuditEntry::query()->where('action', 'projects.project.created')->count())->toBe(1);
    $this->get('/projects')->assertInertia(fn (AssertableInertia $p) => $p->component('Projects::Projects', false)->where('projects.data.0.name', 'GG Operations')->where('projects.data.0.health', 'green'));
    $this->get("/projects/{$project->id}")->assertInertia(fn (AssertableInertia $p) => $p->where('project.members.0.name', $lead->name)->where('form.can.wbs', true));
});

it('turns amber at the threshold and red above the budget', function (int $minutes, string $health) {
    $project = new Project(['budget_minutes' => 18000, 'amber_percent' => 90]);
    expect($project->health($minutes))->toBe($health);
})->with([[16199, 'green'], [16200, 'amber'], [16560, 'amber'], [18000, 'amber'], [18001, 'red']]);

it('shows the health from booked time', function () {
    $project = newProject(projectUser('project_lead'));
    app()->bind(BookedTime::class, fn () => new class($project->id) implements BookedTime
    {
        public function __construct(private string $id) {}

        public function minutesByProject(array $projectIds): array
        {
            return [$this->id => 16560];
        }

        public function minutesByWorkPackage(string $projectId): array
        {
            return [];
        }
    });
    $this->get('/projects')->assertInertia(fn (AssertableInertia $p) => $p->where('projects.data.0.health', 'amber'));
});

it('hides the money budget without permission', function () {
    $controller = projectUser('controller');
    $project = newProject($controller);
    $this->put("/projects/{$project->id}", projectInput(['budget_minor' => 3000000]))->assertSessionHasNoErrors();
    $this->get("/projects/{$project->id}")->assertInertia(fn (AssertableInertia $p) => $p->where('project.budget_minor', 3000000));

    $lead = projectUser('project_lead');
    join_($project, $lead, 'project_lead');
    $this->actingAs($lead)->get("/projects/{$project->id}")->assertInertia(fn (AssertableInertia $p) => $p->missing('project.budget_minor')->where('project.budget_minutes', 18000));
    $this->put("/projects/{$project->id}", projectInput(['budget_minor' => 1]))->assertForbidden();
    $this->put("/projects/{$project->id}", projectInput(['currency' => 'USD']))->assertForbidden();
    $this->put("/projects/{$project->id}", projectInput(['name' => 'Renamed']))->assertSessionHasNoErrors();
    expect($project->refresh()->budget_minor)->toBe(3000000)->and($project->name)->toBe('Renamed');
});

it('shows a project lead only own projects', function () {
    $project = newProject(projectUser('controller'));
    $lead = projectUser('project_lead');

    $this->actingAs($lead)->get('/projects')->assertInertia(fn (AssertableInertia $p) => $p->has('projects.data', 0));
    $this->get("/projects/{$project->id}")->assertForbidden();
    join_($project, $lead, 'project_lead');
    app()->forgetScopedInstances();
    $this->get("/projects/{$project->id}")->assertOk();
    $this->get('/projects')->assertInertia(fn (AssertableInertia $p) => $p->has('projects.data', 1));

    ProjectMember::query()->where('user_id', $lead->id)->update(['left_on' => now()->subDays(2)->toDateString()]);
    app()->forgetScopedInstances();
    $this->get("/projects/{$project->id}")->assertForbidden();
    $this->actingAs(projectUser('employee'))->post('/projects', projectInput())->assertForbidden();
});

it('refuses wrong projects', function (array $changes, string $field) {
    $this->actingAs(projectUser('controller'))->post('/projects', projectInput($changes))->assertSessionHasErrors($field);
})->with([
    'end before start' => [['end_date' => '2026-09-30'], 'end_date'],
    'unknown customer' => [['company_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'], 'company_id'],
    'negative budget' => [['budget_minutes' => -1], 'budget_minutes'],
    'status of a new project' => [['status' => 'closed'], 'status'],
]);

it('manages the team and never grants more than one holds', function () {
    $lead = projectUser('project_lead');
    $project = newProject($lead);
    $employee = projectUser('employee');
    $member = ProjectRole::query()->where('key', 'project_member')->firstOrFail();

    $add = fn (): TestResponse => $this->post("/projects/{$project->id}/members", ['user_id' => $employee->id, 'project_role_id' => $member->id]);
    $add()->assertSessionHasNoErrors();
    $add()->assertSessionHasErrors('user_id');
    $row = ProjectMember::query()->where('user_id', $employee->id)->sole();
    $this->delete("/projects/{$project->id}/members/{$row->id}")->assertSessionHasNoErrors();
    expect(ProjectMember::query()->where('user_id', $employee->id)->exists())->toBeFalse();

    app(RoleManager::class)->grantInProject($member, ['projects.budget.view']);
    app()->forgetScopedInstances();
    $add()->assertForbidden();
});

it('numbers nested work packages and renumbers on move', function () {
    $project = newProject(projectUser('project_lead'));
    $post = fn (string $name, ?string $parent = null): TestResponse => $this->post("/projects/{$project->id}/work-packages", ['name' => $name, 'parent_id' => $parent, 'billable' => true])->assertSessionHasNoErrors();
    $id = fn (string $name): string => WorkPackage::query()->where('name', $name)->sole()->id;
    $codes = fn (): array => WorkPackage::query()->orderBy('path')->pluck('wbs_code', 'name')->all();

    $post('Project management');
    $post('Consulting');
    $post('Development');
    $post('Backend', $id('Development'));
    expect($codes())->toBe(['Project management' => '1', 'Consulting' => '2', 'Development' => '3', 'Backend' => '3.1']);

    $before = $id('Backend');
    $this->post("/projects/{$project->id}/work-packages/{$before}/move", ['parent_id' => $id('Consulting'), 'place' => 0])->assertSessionHasNoErrors();
    expect($codes())->toBe(['Project management' => '1', 'Consulting' => '2', 'Backend' => '2.1', 'Development' => '3'])->and($id('Backend'))->toBe($before);

    $this->post("/projects/{$project->id}/work-packages/{$id('Development')}/move", ['parent_id' => null, 'place' => 0]);
    expect($codes())->toBe(['Development' => '1', 'Project management' => '2', 'Consulting' => '3', 'Backend' => '3.1']);

    $this->post("/projects/{$project->id}/work-packages/{$id('Consulting')}/move", ['parent_id' => $id('Backend'), 'place' => 0])->assertSessionHasErrors('parent_id');
    expect($codes()['Consulting'])->toBe('3');
});

it('keeps more than nine siblings in order and refuses a seventh level', function () {
    $project = newProject(projectUser('project_lead'));
    $wbs = app(WorkBreakdown::class);
    foreach (range(1, 11) as $i) {
        $wbs->add($project, ['name' => "P{$i}", 'billable' => true], null);
    }
    expect(WorkPackage::query()->orderBy('path')->pluck('wbs_code')->all())->toBe(array_map(strval(...), range(1, 11)));

    $parent = WorkPackage::query()->orderBy('path')->firstOrFail()->id;
    foreach (range(2, 6) as $level) {
        $parent = $wbs->add($project, ['name' => "L{$level}", 'billable' => true], $parent)->id;
    }
    expect(fn () => $wbs->add($project, ['name' => 'L7', 'billable' => true], $parent))->toThrow(ValidationException::class)
        ->and(WorkPackage::query()->where('name', 'L7')->exists())->toBeFalse();
});

it('lets only members book, on open packages, in the project period', function () {
    $lead = projectUser('project_lead');
    $project = newProject($lead);
    $package = app(WorkBreakdown::class)->add($project, ['name' => 'Consulting', 'billable' => true], null);
    $employee = projectUser('employee');
    $directory = app(ProjectDirectory::class);

    expect($directory->mayBook($employee->id, $package->id, '2026-11-24'))->toBeFalse();
    join_($project, $employee, 'project_member');
    expect($directory->mayBook($employee->id, $package->id, '2026-11-24'))->toBeTrue()
        ->and($directory->mayBook($employee->id, $package->id, '2026-09-30'))->toBeFalse()
        ->and($directory->mayBook($employee->id, $package->id, '2027-03-31'))->toBeTrue()
        ->and($directory->bookable($employee->id, '2026-11-24')[0]['project'])->toBe('GG Operations');
    $package->update(['status' => 'closed']);
    expect($directory->mayBook($employee->id, $package->id, '2026-11-24'))->toBeFalse()
        ->and($directory->workPackages([$package->id])[$package->id]['name'])->toBe('Consulting');
    $package->update(['status' => 'open']);
    $project->update(['status' => 'on_hold']);
    expect($directory->bookable($employee->id, '2026-11-24'))->toBe([]);
});

it('takes the rounding from the contract of the package', function () {
    $controller = projectUser('controller');
    $project = newProject($controller);
    $this->post("/projects/{$project->id}/contracts", ['number' => 'SOW-2026-03', 'contract_type' => 'time_and_material', 'status' => 'active', 'rounding_minutes' => 15, 'minimum_minutes' => 15])->assertSessionHasNoErrors();
    $this->post("/projects/{$project->id}/contracts", ['number' => 'SOW-2026-03', 'contract_type' => 'retainer', 'status' => 'active', 'rounding_minutes' => 15, 'minimum_minutes' => 15])->assertSessionHasErrors('number');
    $contract = Contract::query()->sole();
    $package = app(WorkBreakdown::class)->add($project, ['name' => 'A', 'billable' => true, 'contract_id' => $contract->id], null);
    $free = app(WorkBreakdown::class)->add($project, ['name' => 'B', 'billable' => true], null);

    expect(app(ProjectDirectory::class)->rounding($package->id))->toBe(['rounding' => 15, 'minimum' => 15])
        ->and(app(ProjectDirectory::class)->rounding($free->id))->toBe(['rounding' => 1, 'minimum' => 0]);
});

it('never mixes projects or tenants', function () {
    $controller = projectUser('controller');
    $project = newProject($controller);
    $second = newProject($controller);
    $package = app(WorkBreakdown::class)->add($second, ['name' => 'Other', 'billable' => true], null);
    $contract = Contract::query()->create(['project_id' => $second->id, 'number' => 'X', 'contract_type' => 'retainer', 'status' => 'active', 'rounding_minutes' => 1, 'minimum_minutes' => 0]);

    $this->put("/projects/{$project->id}/work-packages/{$package->id}", ['name' => 'x', 'billable' => true])->assertNotFound();
    $this->post("/projects/{$project->id}/work-packages", ['name' => 'x', 'billable' => true, 'parent_id' => $package->id])->assertNotFound();
    $this->post("/projects/{$project->id}/work-packages", ['name' => 'x', 'billable' => true, 'contract_id' => $contract->id])->assertSessionHasErrors('contract_id');

    $other = Tenant::factory()->create();
    $foreign = app(TenantContext::class)->run($other, function () {
        $company = projectCompany();

        return Project::query()->create(['number' => 'P-00001', 'name' => 'Foreign', 'company_id' => $company->id, 'status' => 'active', 'billing_model' => 'internal', 'currency' => 'EUR']);
    });
    $foreignUser = User::factory()->for($other)->make()->forceFill(['tenant_id' => $other->id]);
    app(TenantContext::class)->run($other, fn () => $foreignUser->save());

    $this->get("/projects/{$foreign->id}")->assertNotFound();
    $this->put("/projects/{$foreign->id}", projectInput())->assertNotFound();
    $this->get('/projects')->assertInertia(fn (AssertableInertia $p) => $p->has('projects.data', 2));
    $this->post('/projects', projectInput(['company_id' => $foreign->company_id]))->assertSessionHasErrors('company_id');
    $this->post("/projects/{$project->id}/members", ['user_id' => $foreignUser->id, 'project_role_id' => ProjectRole::query()->firstOrFail()->id])->assertNotFound();
});

it('serves the API', function () {
    $user = projectUser('admin');
    $project = newProject($user);
    $secret = app(ApiTokens::class)->create($user, 't', ['projects:read'], 30)['secret'];
    $this->withToken($secret)->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.0.number', 'P-00001');
    $this->getJson("/api/v1/projects/{$project->id}")->assertOk()->assertJsonPath('company', 'Learnteq Demo GmbH');
});

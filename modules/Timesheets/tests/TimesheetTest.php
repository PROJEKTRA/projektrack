<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Api\ApiTokens;
use App\Authorization\RoleManager;
use App\Models\AppNotification;
use App\Models\AuditEntry;
use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Settings\Settings;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Modules\Crm\Domain\Company;
use Modules\Projects\Contracts\BookedTime;
use Modules\Projects\Domain\Contract;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\ProjectMember;
use Modules\Projects\Domain\WorkBreakdown;
use Modules\Projects\Domain\WorkPackage;
use Modules\Timesheets\Contracts\ApprovedTime;
use Modules\Timesheets\Domain\Rounding;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\Timer;
use Modules\Timesheets\Domain\Timers;
use Modules\Timesheets\Domain\Timesheets;

/*
 * S-0944 booking and rounding, S-0945 week, S-0947 approval and correction,
 * S-0948 month close.
 */

beforeEach(function () {
    app(TenantModules::class)->enable('timesheets');
    $this->travelTo('2026-11-05 09:00:00');
    [$this->project, $this->package] = timeProject();
    $this->employee = timeUser('employee');
    timeJoin($this->project, $this->employee, 'project_member');
});

function timeUser(string $role): User
{
    $user = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($user, Role::query()->where('key', $role)->firstOrFail());

    return $user;
}

/** @return array{0: Project, 1: WorkPackage} */
function timeProject(string $number = 'P-00001'): array
{
    $company = Company::query()->firstOrCreate(['number' => 'C-00001'], ['name' => 'Demo', 'kind' => 'customer', 'status' => 'active', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'pdf']);
    $project = Project::query()->create(['number' => $number, 'name' => 'GG Operations', 'company_id' => $company->id, 'status' => 'active', 'billing_model' => 'time_and_material', 'currency' => 'EUR', 'budget_minutes' => 6000]);

    return [$project, app(WorkBreakdown::class)->add($project, ['name' => 'Consulting', 'billable' => true], null)];
}

function timeJoin(Project $project, User $user, string $role): void
{
    ProjectMember::query()->create(['project_id' => $project->id, 'user_id' => $user->id, 'project_role_id' => ProjectRole::query()->where('key', $role)->firstOrFail()->id]);
    app()->forgetScopedInstances();
}

function book(User $user, int $minutes, string $date = '2026-11-03', ?string $note = null, ?string $package = null): TestResponse
{
    return test()->actingAs($user)->putJson('/timesheets/cells', ['work_package_id' => $package ?? test()->package->id, 'date' => $date, 'minutes' => $minutes, 'description' => $note]);
}

function approved(int $minutes, string $date = '2026-11-03'): TimeEntry
{
    $entry = app(Timesheets::class)->book(test()->employee, test()->package->id, $date, $minutes, null);
    app(Timesheets::class)->submitWeek(test()->employee, $date);
    app(Timesheets::class)->approve(timeUser('controller'), [$entry->id]);

    return $entry->refresh();
}

it('rounds up by step and minimum', function (int $minutes, int $step, int $minimum, int $billable) {
    expect(Rounding::billable($minutes, $step, $minimum))->toBe($billable);
})->with([[22, 15, 15, 30], [30, 15, 15, 30], [31, 15, 15, 45], [1, 15, 15, 15], [5, 1, 0, 5], [5, 1, 30, 30], [20, 15, 20, 30], [0, 15, 15, 0], [-60, 15, 15, 0], [480, 60, 0, 480], [7, 0, 0, 7]]);

it('stores whole minutes, keeps the original and bills by the contract', function () {
    $contract = Contract::query()->create(['project_id' => $this->project->id, 'number' => 'SOW-2026-03', 'contract_type' => 'time_and_material', 'status' => 'active', 'rounding_minutes' => 15, 'minimum_minutes' => 15]);
    $this->package->update(['contract_id' => $contract->id]);

    book($this->employee, 22)->assertOk()->assertJsonPath('entry.duration_minutes', 22)->assertJsonPath('entry.billable_minutes', 30);
    expect(TimeEntry::query()->sole()->work_date->toDateString())->toBe('2026-11-03');

    $this->package->update(['billable' => false]);
    book($this->employee, 25)->assertJsonPath('entry.billable_minutes', 0);
    expect(TimeEntry::query()->count())->toBe(1);
});

it('empties a cell with zero and has one entry per cell', function () {
    book($this->employee, 60);
    book($this->employee, 90);
    expect(TimeEntry::query()->sole()->duration_minutes)->toBe(90);
    book($this->employee, 0)->assertOk()->assertJsonPath('entry', null);
    expect(TimeEntry::query()->count())->toBe(0);
});

it('refuses what must not be booked', function (Closure $prepare, array $cell, string $field) {
    $prepare->call($this);
    book($this->employee, ...$cell)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'closed package' => [fn () => $this->package->update(['status' => 'closed']), [60], 'work_package_id'],
    'left the team' => [fn () => ProjectMember::query()->update(['left_on' => '2026-11-01']), [60], 'work_package_id'],
    'inactive project' => [fn () => $this->project->update(['status' => 'closed']), [60], 'work_package_id'],
    'more than a day' => [fn () => null, [1441], 'minutes'],
    'negative' => [fn () => null, [-1], 'minutes'],
    'no date' => [fn () => null, [60, '2026-02-30'], 'date'],
    'locked month' => [fn () => app(Timesheets::class)->setLocked(timeUser('controller'), '2026-11', true, null), [60], 'date'],
]);

it('allows no more than 24 hours on a day over all packages', function () {
    $second = app(WorkBreakdown::class)->add($this->project, ['name' => 'B', 'billable' => true], null);
    book($this->employee, 1000)->assertOk();
    book($this->employee, 441, package: $second->id)->assertUnprocessable()->assertJsonValidationErrors('minutes');
    book($this->employee, 440, package: $second->id)->assertOk();
});

it('requires a description by the rule of the company', function () {
    app(Settings::class)->save('timesheets', ['description_from_minutes' => 30, 'day_warning_minutes' => 600], timeUser('admin'));
    app()->forgetScopedInstances();
    book($this->employee, 45)->assertUnprocessable()->assertJsonValidationErrors('description')->assertJsonPath('errors.description.0', 'From 30 minutes a description is required.');
    book($this->employee, 29)->assertOk();
    book($this->employee, 45, note: 'Workshop')->assertOk();
    book($this->employee, 45, note: '   ')->assertUnprocessable();
});

it('rolls time up into work package and project, and shows no amounts', function () {
    $second = app(WorkBreakdown::class)->add($this->project, ['name' => 'B', 'billable' => true], null);
    book($this->employee, 60);
    book($this->employee, 30, '2026-11-04');
    book($this->employee, 15, package: $second->id);

    expect(app(BookedTime::class)->minutesByProject([$this->project->id]))->toBe([$this->project->id => 105])
        ->and(app(BookedTime::class)->minutesByWorkPackage($this->project->id))->toEqualCanonicalizing([$this->package->id => 90, $second->id => 15]);

    $page = $this->actingAs($this->employee)->get('/timesheets?date=2026-11-04')->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Timesheets::Week', false)->where('from', '2026-11-02')->has('days', 7)->has('entries', 3)->has('packages', 2));
    expect((string) $page->getContent())->not->toContain('rate')->not->toContain('budget');
});

it('knows the week across year ends', function (string $date, string $from, string $to) {
    expect(Timesheets::week($date))->toBe([$from, $to]);
})->with([['2026-11-02', '2026-11-02', '2026-11-08'], ['2026-11-08', '2026-11-02', '2026-11-08'], ['2027-01-01', '2026-12-28', '2027-01-03'], ['2028-02-29', '2028-02-28', '2028-03-05'], ['2026-03-29', '2026-03-23', '2026-03-29']]);

it('submits a week and lets the project lead approve it', function () {
    $lead = timeUser('project_lead');
    timeJoin($this->project, $lead, 'project_lead');
    book($this->employee, 60);
    book($this->employee, 45, '2026-11-10');
    $this->post('/timesheets/submit', ['date' => '2026-11-05'])->assertSessionHasNoErrors();
    expect(TimeEntry::query()->where('status', 'submitted')->count())->toBe(1);
    // Submitted time is out of the owner's hands; a new draft may stand beside it.
    book($this->employee, 0)->assertOk();
    $id = TimeEntry::query()->where('status', 'submitted')->sole()->id;

    $this->actingAs($lead)->get('/timesheets/approvals')->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 1)->where('entries.0.user', $this->employee->name));
    $this->post('/timesheets/approvals', ['ids' => [$id], 'decision' => 'approve'])->assertSessionHasNoErrors();
    $entry = TimeEntry::query()->findOrFail($id);
    expect($entry->status)->toBe('approved')->and($entry->approved_by)->toBe($lead->id)->and($entry->approved_at)->not->toBeNull()
        ->and(AuditEntry::query()->where('action', 'timesheets.entry.approved')->count())->toBe(1);
});

it('returns time with a reason and tells the person once', function () {
    book($this->employee, 60);
    book($this->employee, 60, '2026-11-04');
    app(Timesheets::class)->submitWeek($this->employee, '2026-11-03');
    $ids = TimeEntry::query()->pluck('id')->all();

    $this->actingAs(timeUser('controller'))->post('/timesheets/approvals', ['ids' => $ids, 'decision' => 'reject'])->assertSessionHasErrors('reason');
    $this->post('/timesheets/approvals', ['ids' => $ids, 'decision' => 'reject', 'reason' => 'wrong work package'])->assertSessionHasNoErrors();
    expect(TimeEntry::query()->where('status', 'rejected')->count())->toBe(2)
        ->and(AppNotification::query()->where('user_id', $this->employee->id)->count())->toBe(1);

    book($this->employee, 30)->assertOk();
    app(Timesheets::class)->submitWeek($this->employee, '2026-11-03');
    expect(TimeEntry::query()->where('status', 'submitted')->count())->toBe(2)->and(TimeEntry::query()->whereNotNull('reject_reason')->count())->toBe(0);
});

it('never lets anyone approve own time or time of other projects', function () {
    $lead = timeUser('project_lead');
    timeJoin($this->project, $lead, 'project_lead');
    [$other] = timeProject('P-00002');
    $otherLead = timeUser('project_lead');
    timeJoin($other, $otherLead, 'project_lead');

    foreach ([$lead, $this->employee] as $user) {
        app(Timesheets::class)->book($user, $this->package->id, '2026-11-03', 60, null);
        app(Timesheets::class)->submitWeek($user, '2026-11-03');
    }
    $own = TimeEntry::query()->where('user_id', $lead->id)->sole();
    $this->actingAs($lead)->post('/timesheets/approvals', ['ids' => [$own->id], 'decision' => 'approve']);
    expect($own->refresh()->status)->toBe('submitted');
    $this->actingAs($otherLead)->get('/timesheets/approvals')->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 0));
    $this->post('/timesheets/approvals', ['ids' => TimeEntry::query()->pluck('id')->all(), 'decision' => 'approve']);
    expect(TimeEntry::query()->where('status', 'approved')->count())->toBe(0);
    $this->actingAs($this->employee)->get('/timesheets/approvals')->assertForbidden();
    $this->post('/timesheets/approvals', ['ids' => [$own->id], 'decision' => 'approve'])->assertForbidden();
});

it('never changes or deletes approved time', function () {
    $entry = approved(240);
    book($this->employee, 60)->assertOk();
    expect($entry->refresh()->duration_minutes)->toBe(240)->and(TimeEntry::query()->count())->toBe(2)
        ->and(fn () => $entry->update(['duration_minutes' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(fn () => $this->employee->forceDelete())->toThrow(Exception::class);
});

it('keeps time that was not billable as it was approved', function () {
    $this->package->update(['billable' => false]);
    $entry = approved(60);
    $this->package->update(['billable' => true]);
    $this->actingAs(timeUser('controller'))->post("/timesheets/entries/{$entry->id}/corrections", ['minutes' => 45, 'reason' => 'typing error'])->assertSessionHasNoErrors();
    expect(TimeEntry::query()->whereNotNull('corrects_id')->sole()->billable_minutes)->toBe(0);
});

it('corrects with an entry for the difference', function () {
    $contract = Contract::query()->create(['project_id' => $this->project->id, 'number' => 'X', 'contract_type' => 'time_and_material', 'status' => 'active', 'rounding_minutes' => 60, 'minimum_minutes' => 0]);
    $this->package->update(['contract_id' => $contract->id]);
    $entry = approved(230);
    expect($entry->billable_minutes)->toBe(240);
    $controller = timeUser('controller');

    $this->actingAs($controller)->post("/timesheets/entries/{$entry->id}/corrections", ['minutes' => 170, 'reason' => 'typing error'])->assertSessionHasNoErrors();
    $correction = TimeEntry::query()->whereNotNull('corrects_id')->sole();
    expect($correction->duration_minutes)->toBe(-60)->and($correction->billable_minutes)->toBe(-60)->and($correction->approved_by)->toBe($controller->id)
        ->and($entry->refresh()->duration_minutes)->toBe(230);

    // A second correction starts from the corrected value.
    $this->post("/timesheets/entries/{$entry->id}/corrections", ['minutes' => 230, 'reason' => 'was right'])->assertSessionHasNoErrors();
    expect(TimeEntry::query()->sum('duration_minutes'))->toEqual(230)->and(TimeEntry::query()->sum('billable_minutes'))->toEqual(240);
    $this->post("/timesheets/entries/{$entry->id}/corrections", ['minutes' => 230, 'reason' => 'x'])->assertSessionHasErrors('minutes');
    $this->post("/timesheets/entries/{$correction->id}/corrections", ['minutes' => 1, 'reason' => 'x'])->assertSessionHasErrors('minutes');
    $this->actingAs($this->employee)->post("/timesheets/entries/{$entry->id}/corrections", ['minutes' => 1, 'reason' => 'x'])->assertForbidden();

    $rows = app(ApprovedTime::class)->between($this->project->id, '2026-11-01', '2026-11-30');
    expect($rows)->toHaveCount(3)->and(array_sum(array_column($rows, 'billable_minutes')))->toBe(240);

    // The sums count the same, first and last day included.
    $sums = app(ApprovedTime::class)->sums($rows[0]['date'], $rows[2]['date'])[$this->project->id];
    expect(array_column($sums, 'billable_minutes', 'id'))->toEqualCanonicalizing(array_column($rows, 'billable_minutes', 'id'))
        ->and(app(ApprovedTime::class)->sums('2026-12-01', '2026-12-31'))->toBe([]);
});

it('locks a month for bookings, submissions, approvals and corrections', function () {
    $entry = approved(60);
    book($this->employee, 30, '2026-11-04');
    $controller = timeUser('controller');
    $this->actingAs($controller)->get('/timesheets/periods?month=2026-11')->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 2)->where('unapproved', 1)->where('nextPage', null)->where('locked', false));
    $this->post('/timesheets/periods', ['month' => '2026-11', 'locked' => true])->assertSessionHasNoErrors();

    book($this->employee, 0, '2026-11-04')->assertUnprocessable();
    $this->actingAs($this->employee)->post('/timesheets/submit', ['date' => '2026-11-04'])->assertSessionHasErrors('date');
    $this->actingAs($controller)->post("/timesheets/entries/{$entry->id}/corrections", ['minutes' => 1, 'reason' => 'x'])->assertSessionHasErrors('date');
    book($this->employee, 30, '2026-12-01')->assertOk();

    $this->actingAs($controller)->post('/timesheets/periods', ['month' => '2026-11', 'locked' => false])->assertSessionHasErrors('reason');
    $this->post('/timesheets/periods', ['month' => '2026-11', 'locked' => false, 'reason' => 'late booking'])->assertSessionHasNoErrors();
    expect(AuditEntry::query()->where('action', 'timesheets.period.reopened')->sole()->contextData()['reason'])->toBe('late booking');
    book($this->employee, 45, '2026-11-04')->assertOk();
    $this->post('/timesheets/periods', ['month' => '2026-11', 'locked' => true])->assertForbidden();
});

it('measures real minutes per local day', function (string $from, string $to, array $parts) {
    expect(Timers::split(CarbonImmutable::parse($from), CarbonImmutable::parse($to), 'Europe/Sarajevo'))->toBe($parts);
})->with([
    'a morning' => ['2026-11-05 08:00:00Z', '2026-11-05 09:10:59Z', ['2026-11-05' => 70]],
    'over midnight' => ['2026-11-05 22:30:00Z', '2026-11-05 23:45:00Z', ['2026-11-05' => 30, '2026-11-06' => 45]],
    'clocks back' => ['2026-10-24 23:00:00Z', '2026-10-25 02:00:00Z', ['2026-10-25' => 180]],
    'clocks forward' => ['2026-03-28 23:00:00Z', '2026-03-29 02:00:00Z', ['2026-03-29' => 180]],
    'seconds' => ['2026-11-05 08:00:00Z', '2026-11-05 08:00:59Z', []],
]);

it('books the timer, adds to the cell and runs only once', function () {
    $second = app(WorkBreakdown::class)->add($this->project, ['name' => 'B', 'billable' => true], null);
    $this->tenant->update(['timezone' => 'Europe/Sarajevo']);
    book($this->employee, 20, '2026-11-05', 'Call');
    $this->actingAs($this->employee)->post('/timesheets/timer', ['work_package_id' => $this->package->id])->assertSessionHasNoErrors();
    $this->travel(70)->minutes();
    // A second timer stops and books the first.
    $this->post('/timesheets/timer', ['work_package_id' => $second->id])->assertSessionHasNoErrors();
    expect(Timer::query()->sole()->work_package_id)->toBe($second->id)->and(TimeEntry::query()->sole()->duration_minutes)->toBe(90);
    $this->travel(15)->minutes();
    $this->get('/timesheets')->assertInertia(fn (AssertableInertia $p) => $p->where('timer.work_package_id', $second->id));
    $this->post('/timesheets/timer/stop', ['description' => 'Review'])->assertSessionHasNoErrors();
    expect(Timer::query()->count())->toBe(0)->and(TimeEntry::query()->where('work_package_id', $second->id)->sole()->description)->toBe('Review');

    $this->post('/timesheets/timer', ['work_package_id' => $second->id]);
    $this->travel(5)->minutes();
    app(Timesheets::class)->setLocked(timeUser('controller'), '2026-11', true, null);
    $this->post('/timesheets/timer/stop')->assertSessionHasErrors('date');
    expect(Timer::query()->count())->toBe(1);
    $this->delete('/timesheets/timer');
    expect(Timer::query()->count())->toBe(0);
});

it('refuses a timer where the user cannot book', function () {
    $this->actingAs(timeUser('employee'))->post('/timesheets/timer', ['work_package_id' => $this->package->id])->assertSessionHasErrors('work_package_id');
});

it('warns once per threshold of the contract budget', function () {
    $contract = Contract::query()->create(['project_id' => $this->project->id, 'number' => 'SOW', 'contract_type' => 'time_and_material', 'status' => 'active', 'rounding_minutes' => 1, 'minimum_minutes' => 0, 'budget_minutes' => 600]);
    $this->package->update(['contract_id' => $contract->id]);
    $lead = timeUser('project_lead');
    timeJoin($this->project, $lead, 'project_lead');
    $count = fn (): int => AppNotification::query()->where('user_id', $lead->id)->where('type', 'projects.budget')->count();

    approved(419);
    expect($count())->toBe(0);
    approved(1, '2026-11-04');
    expect($count())->toBe(1);
    approved(60, '2026-10-05');
    expect($count())->toBe(1);
    // 90 and 100 percent at once are one notice.
    approved(120, '2026-10-06');
    expect($count())->toBe(2)->and(AppNotification::query()->where('user_id', $this->employee->id)->where('type', 'projects.budget')->count())->toBe(0);
});

it('never reaches another tenant', function () {
    $entry = approved(60);
    $other = Tenant::factory()->create();
    [$package, $user] = app(TenantContext::class)->run($other, function () {
        [$project, $package] = timeProject();
        $user = User::factory()->create();

        return [$package, $user];
    });
    book($this->employee, 60, package: $package->id)->assertUnprocessable();
    app(TenantContext::class)->run($other, function () use ($entry) {
        expect(TimeEntry::query()->count())->toBe(0)->and(app(ApprovedTime::class)->between($entry->project_id, '2026-01-01', '2026-12-31'))->toBe([]);
    });
});

it('keeps one entry per cell when the submitted time comes back beside a new draft', function (int $minutes) {
    book($this->employee, 60)->assertOk();
    app(Timesheets::class)->submitWeek($this->employee, '2026-11-03');
    book($this->employee, 30)->assertOk();
    $submitted = TimeEntry::query()->where('status', 'submitted')->sole();
    $this->actingAs(timeUser('controller'))->post('/timesheets/approvals', ['ids' => [$submitted->id], 'decision' => 'reject', 'reason' => 'wrong'])->assertSessionHasNoErrors();

    // Nothing booked is lost: both are one entry now.
    expect((int) TimeEntry::query()->whereIn('status', TimeEntry::EDITABLE)->sole()->duration_minutes)->toBe(90);

    // Setting the cell sets it: nothing the owner may change stays beside the new value.
    book($this->employee, $minutes)->assertOk();
    expect((int) TimeEntry::query()->whereIn('status', TimeEntry::EDITABLE)->sum('duration_minutes'))->toBe($minutes);
})->with(['changed' => 45, 'emptied' => 0]);

it('refuses decisions in a locked month', function (array $decision) {
    book($this->employee, 60)->assertOk();
    app(Timesheets::class)->submitWeek($this->employee, '2026-11-03');
    $entry = TimeEntry::query()->sole();
    $controller = timeUser('controller');
    app(Timesheets::class)->setLocked($controller, '2026-11', true, null);

    $this->actingAs($controller)->post('/timesheets/approvals', ['ids' => [$entry->id], ...$decision])->assertSessionHasErrors('date');
    expect($entry->refresh()->status)->toBe('submitted');
})->with(['approve' => [['decision' => 'approve']], 'reject' => [['decision' => 'reject', 'reason' => 'wrong']]]);

it('sends no budget notice to a lead who left the project', function () {
    $contract = Contract::query()->create(['project_id' => $this->project->id, 'number' => 'SOW', 'contract_type' => 'time_and_material', 'status' => 'active', 'rounding_minutes' => 1, 'minimum_minutes' => 0, 'budget_minutes' => 100]);
    $this->package->update(['contract_id' => $contract->id]);
    $former = timeUser('project_lead');
    timeJoin($this->project, $former, 'project_lead');
    ProjectMember::query()->where('user_id', $former->id)->update(['left_on' => '2026-10-31']);
    $lead = timeUser('project_lead');
    timeJoin($this->project, $lead, 'project_lead');

    approved(80);
    $count = fn (User $u): int => AppNotification::query()->where('user_id', $u->id)->where('type', 'projects.budget')->count();
    expect($count($lead))->toBe(1)->and($count($former))->toBe(0);
});

it('takes the limits of a day and the leap day', function (int $minutes, string $date, int $status) {
    book($this->employee, $minutes, $date)->assertStatus($status);
    expect(TimeEntry::query()->count())->toBe($status === 200 ? 1 : 0);
})->with([
    'a whole day' => [1440, '2026-11-03', 200], 'one minute' => [1, '2026-11-03', 200], 'leap day' => [60, '2028-02-29', 200],
    'no leap year' => [60, '2027-02-29', 422], 'before 2000' => [60, '2000-01-01', 422], 'after 2099' => [60, '2100-01-01', 422],
]);

it('does not count bookings against the limits of other pages', function () {
    // 240 cells a minute are allowed; saving the profile has its own limit of 30.
    foreach (range(1, 30) as $minutes) {
        book($this->employee, $minutes)->assertOk();
    }
    $this->patch('/settings/profile', ['name' => 'Renamed Person', 'email' => $this->employee->email])->assertRedirect();
    expect($this->employee->refresh()->name)->toBe('Renamed Person');
});

it('lets nobody correct the own time', function () {
    $controller = timeUser('controller');
    timeJoin($this->project, $controller, 'project_member');
    $entry = app(Timesheets::class)->book($controller, $this->package->id, '2026-11-03', 60, null);
    app(Timesheets::class)->submitWeek($controller, '2026-11-03');
    app(Timesheets::class)->approve(timeUser('admin'), [$entry->id]);

    $this->actingAs($controller)->post("/timesheets/entries/{$entry->id}/corrections", ['minutes' => 600, 'reason' => 'more'])->assertForbidden();
    expect(TimeEntry::query()->sole()->duration_minutes)->toBe(60);
});

it('allows no more than 24 hours on a day by correction', function () {
    $second = app(WorkBreakdown::class)->add($this->project, ['name' => 'B', 'billable' => true], null);
    $entry = approved(1000);
    book($this->employee, 400, package: $second->id)->assertOk();
    $correct = fn (int $minutes): TestResponse => $this->actingAs(timeUser('controller'))->post("/timesheets/entries/{$entry->id}/corrections", ['minutes' => $minutes, 'reason' => 'x']);

    $correct(1041)->assertSessionHasErrors('minutes');
    $correct(1040)->assertSessionHasNoErrors();
    expect((int) TimeEntry::query()->sum('duration_minutes'))->toBe(1440);
});

it('measures whole days of 23 and 25 hours', function (string $from, string $to, array $parts) {
    expect(Timers::split(CarbonImmutable::parse($from), CarbonImmutable::parse($to), 'Europe/Sarajevo'))->toBe($parts);
})->with([
    'clocks forward' => ['2026-03-28 23:00:00Z', '2026-03-29 22:00:00Z', ['2026-03-29' => 1380]],
    'clocks back' => ['2026-10-24 22:00:00Z', '2026-10-25 23:00:00Z', ['2026-10-25' => 1500]],
    'over the leap day' => ['2028-02-28 22:30:00Z', '2028-02-29 23:30:00Z', ['2028-02-28' => 30, '2028-02-29' => 1440, '2028-03-01' => 30]],
]);

it('serves the API for the own time', function () {
    $admin = timeUser('admin');
    timeJoin($this->project, $admin, 'project_member');
    $secret = app(ApiTokens::class)->create($admin, 't', ['timesheets:read', 'timesheets:write'], 30)['secret'];
    app(Timesheets::class)->book($this->employee, $this->package->id, '2026-11-03', 60, null);

    $this->withToken($secret)->putJson('/api/v1/timesheets/entries', ['work_package_id' => $this->package->id, 'date' => '2026-11-03', 'minutes' => 75])->assertOk()->assertJsonPath('entry.duration_minutes', 75);
    $this->getJson('/api/v1/timesheets/entries?from=2026-11-01&to=2026-11-30')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.duration_minutes', 75);
    $this->getJson('/api/v1/timesheets/entries?from=2026-01-01&to=2026-11-30')->assertUnprocessable();
});

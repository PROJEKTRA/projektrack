<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Tenancy\TenantContext;
use Illuminate\Testing\TestResponse;
use Modules\Crm\Domain\Company;
use Modules\Projects\Domain\Contract;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\ProjectMember;
use Modules\Projects\Domain\WorkBreakdown;
use Modules\Rates\Contracts\TimeValues;
use Modules\Rates\Domain\RateRule;
use Modules\Rates\Domain\Rates;
use Modules\Rates\Domain\Valuation;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\Timesheets;

/*
 * S-0950: rate hierarchy, validity, frozen values.
 */

beforeEach(function () {
    app(TenantModules::class)->enable('rates');
    $this->travelTo('2026-11-05 09:00:00');
    $this->company = Company::query()->create(['number' => 'C-00001', 'name' => 'Demo', 'kind' => 'customer', 'status' => 'active', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'pdf']);
    $this->project = Project::query()->create(['number' => 'P-00001', 'name' => 'GG', 'company_id' => $this->company->id, 'status' => 'active', 'billing_model' => 'time_and_material', 'currency' => 'EUR']);
    $this->contract = Contract::query()->create(['project_id' => $this->project->id, 'number' => 'SOW-2026-03', 'contract_type' => 'time_and_material', 'status' => 'active', 'rounding_minutes' => 1, 'minimum_minutes' => 0]);
    $this->package = app(WorkBreakdown::class)->add($this->project, ['name' => 'Consulting', 'billable' => true, 'contract_id' => $this->contract->id], null);
    $this->employee = rateUser('employee');
    $this->controller = rateUser('controller');
    ProjectMember::query()->create(['project_id' => $this->project->id, 'user_id' => $this->employee->id, 'project_role_id' => ProjectRole::query()->where('key', 'project_member')->firstOrFail()->id]);
});

function rateUser(string $role): User
{
    $user = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($user, Role::query()->where('key', $role)->firstOrFail());

    return $user;
}

function rate(string $scope, ?string $id, string $rate, string $from = '2026-01-01'): RateRule
{
    return app(Rates::class)->add($scope, $id, $rate, 'EUR', $from, test()->controller);
}

function approve_(string $date, int $minutes): TimeEntry
{
    $entry = app(Timesheets::class)->book(test()->employee, test()->package->id, $date, $minutes, null);
    app(Timesheets::class)->submitWeek(test()->employee, $date);
    app(Timesheets::class)->approve(test()->controller, [$entry->id]);

    return $entry;
}

it('takes the most specific rate', function () {
    $resolve = fn (): array => [app(Rates::class)->resolve($this->employee->id, $this->package->id, '2026-11-03')?->scope, app(Rates::class)->resolve($this->employee->id, $this->package->id, '2026-11-03')?->rate];
    expect($resolve())->toBe([null, null]);
    foreach ([['tenant', null, '70'], ['user', $this->employee->id, '80'], ['company', $this->company->id, '85'], ['project', $this->project->id, '90'], ['contract', $this->contract->id, '95'], ['work_package', $this->package->id, '99.5']] as [$scope, $id, $value]) {
        rate($scope, $id, $value);
        expect($resolve())->toBe([$scope, number_format((float) $value, 4, '.', '')]);
    }
    rate('user', $this->controller->id, '500');
    expect($resolve()[0])->toBe('work_package');
});

it('values each day with the rate of that day', function () {
    rate('tenant', null, '100');
    rate('tenant', null, '110', '2026-11-01');
    expect(RateRule::query()->orderBy('valid_from')->first()?->valid_to?->toDateString())->toBe('2026-10-31');

    approve_('2026-10-31', 90);
    approve_('2026-11-01', 90);
    expect(Valuation::query()->orderBy('amount_minor')->pluck('amount_minor')->all())->toBe([15000, 16500]);
});

it('freezes the value at approval', function () {
    rate('tenant', null, '100');
    $entry = approve_('2026-11-03', 20);
    rate('project', $this->project->id, '200');
    expect(app(TimeValues::class)->of([$entry->id])[$entry->id])->toBe(['rate' => '100.0000', 'currency' => 'EUR', 'minutes' => 20, 'amount_minor' => 3333, 'source' => 'tenant'])
        ->and(fn () => Valuation::query()->sole()->update(['amount_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => Valuation::query()->sole()->delete())->toThrow(LogicException::class);

    // A correction keeps the rate of its entry.
    app(Timesheets::class)->correct($this->controller, $entry->refresh(), 80, 'forgot an hour');
    expect(Valuation::query()->sum('amount_minor'))->toEqual(13333)->and(Valuation::query()->count())->toBe(2);
});

it('leaves time without a rate without a value', function () {
    $entry = approve_('2026-11-03', 60);
    expect(app(TimeValues::class)->of([$entry->id]))->toBe([]);
    app(Timesheets::class)->correct($this->controller, $entry->refresh(), 30, 'x');
    expect(Valuation::query()->count())->toBe(0);
});

it('never overlaps and never edits', function () {
    rate('tenant', null, '100', '2026-06-01');
    $this->actingAs($this->controller)->post('/rates', ['scope' => 'tenant', 'rate' => '90', 'currency' => 'EUR', 'valid_from' => '2026-06-01'])->assertSessionHasErrors('valid_from');
    $this->post('/rates', ['scope' => 'tenant', 'rate' => '90,5', 'currency' => 'EUR', 'valid_from' => '2026-07-01'])->assertSessionHasNoErrors();
    $last = RateRule::query()->orderByDesc('valid_from')->firstOrFail();
    expect($last->rate)->toBe('90.5000');
    $first = RateRule::query()->orderBy('valid_from')->firstOrFail();
    $this->post("/rates/{$first->id}/end", ['valid_to' => '2026-12-31'])->assertSessionHasErrors('valid_to');
    $this->post("/rates/{$last->id}/end", ['valid_to' => '2026-06-30'])->assertSessionHasErrors('valid_to');
    $this->post("/rates/{$last->id}/end", ['valid_to' => '2026-12-31'])->assertSessionHasNoErrors();
    expect(app(Rates::class)->resolve($this->employee->id, $this->package->id, '2027-01-01'))->toBeNull();
});

it('refuses wrong input', function (array $changes, string $field) {
    $this->actingAs($this->controller)->post('/rates', array_replace(['scope' => 'tenant', 'rate' => '90', 'currency' => 'EUR', 'valid_from' => '2026-07-01'], $changes))->assertSessionHasErrors($field);
})->with([[['rate' => '-1'], 'rate'], [['rate' => '1e3'], 'rate'], [['currency' => 'XXQ'], 'currency'], [['scope' => 'project'], 'scope_id'], [['scope' => 'user', 'scope_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'], 'scope_id']]);

it('values in the currency of the project with the rate of the day', function () {
    app(Rates::class)->add('tenant', null, '195.583', 'BAM', '2026-01-01', $this->controller);
    $entry = approve_('2026-11-03', 90);
    expect(app(TimeValues::class)->of([$entry->id])[$entry->id])->toBe(['rate' => '100.0000', 'currency' => 'EUR', 'minutes' => 90, 'amount_minor' => 15000, 'source' => 'tenant'])
        ->and(Valuation::query()->sole()->rule_currency)->toBe('BAM')->and(Valuation::query()->sole()->fx_rate)->toBe('0.5112918812');

    app(Rates::class)->add('user', $this->employee->id, '110', 'USD', '2026-01-01', $this->controller);
    $waiting = approve_('2026-11-04', 60);
    expect(app(TimeValues::class)->of([$waiting->id]))->toBe([]);
    $add = fn (array $c): TestResponse => $this->actingAs($this->controller)->post('/rates/exchange', array_replace(['from_currency' => 'USD', 'to_currency' => 'EUR', 'rate' => '0,9', 'rate_date' => '2026-11-01'], $c));
    $add(['rate_date' => '2026-11-05'])->assertSessionHasNoErrors();
    $add([])->assertSessionHasNoErrors();
    $add([])->assertSessionHasErrors('rate');
    $add(['rate' => '0', 'rate_date' => '2026-10-01'])->assertSessionHasErrors('rate');
    $add(['from_currency' => 'BAM'])->assertSessionHasErrors('rate');
    $add(['rate_date' => '2026-11-06'])->assertSessionHasErrors('rate_date');
    $row = fn (TimeEntry $e): array => ['id' => $e->id, 'user_id' => $e->user_id, 'work_package_id' => $e->work_package_id, 'project_id' => $e->project_id, 'date' => $e->work_date->toDateString(), 'billable_minutes' => $e->billable_minutes, 'corrects' => null];
    expect(app(TimeValues::class)->valueMissing([$row($waiting->refresh())], $this->controller))->toBe(1)
        ->and(app(TimeValues::class)->of([$waiting->id])[$waiting->id]['amount_minor'])->toBe(9900)
        ->and(app(TimeValues::class)->valueMissing([$row($waiting)], $this->controller))->toBe(0);
});

it('is for those who manage rates, in their tenant', function () {
    $this->actingAs($this->employee)->get('/rates')->assertForbidden();
    $this->actingAs(rateUser('project_lead'))->get('/rates')->assertForbidden();
    $rule = rate('tenant', null, '100');
    app(TenantContext::class)->run(Tenant::factory()->create(), fn () => expect(RateRule::query()->count())->toBe(0));
    $this->actingAs($this->controller)->get('/rates')->assertOk()->assertSee($rule->id);
});

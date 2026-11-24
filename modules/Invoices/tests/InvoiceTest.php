<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use App\Authorization\RoleManager;
use App\Documents\Numbers;
use App\I18n\RegionalFormat;
use App\I18n\StringEditor;
use App\Models\AuditEntry;
use App\Models\ProjectRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\TenantModules;
use App\Settings\Settings;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Modules\Crm\Domain\Companies;
use Modules\Crm\Domain\Company;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceDelivery;
use Modules\Invoices\Domain\InvoiceLine;
use Modules\Invoices\Domain\InvoicePaid;
use Modules\Invoices\Domain\Invoices;
use Modules\Invoices\Domain\InvoiceTime;
use Modules\Projects\Domain\Project;
use Modules\Projects\Domain\ProjectMember;
use Modules\Projects\Domain\WorkBreakdown;
use Modules\Rates\Domain\Rates;
use Modules\ServiceReports\Domain\ServiceReport;
use Modules\Timesheets\Domain\TimeEntry;
use Modules\Timesheets\Domain\Timesheets;
use PhpOffice\PhpSpreadsheet\IOFactory;

/*
 * S-0961 drafts, S-0962 numbers, S-0963 finalization and cancellation, S-0964
 * PDF, S-0965 fiscal reference, S-0966 payments; S-0953, S-0954, S-0957 reports.
 */

beforeEach(function () {
    $this->travelTo('2026-11-05 09:00:00');
    $this->tenant->update(['country_code' => 'DE', 'base_currency' => 'EUR', 'timezone' => 'Europe/Berlin']);
    app(TenantModules::class)->enable('service_reports');
    app(TenantModules::class)->enable('invoices');
    $this->controller = invUser('controller');
    $this->withSession(['auth.password_confirmed_at' => time()]);
    sellerData();
    $this->company = invCompany('domestic', 'DE', null);
});

function invUser(string $role): User
{
    $user = User::factory()->withTwoFactor()->create(['locale' => 'en']);
    app(RoleManager::class)->assign($user, Role::query()->where('key', $role)->firstOrFail());

    return $user;
}

/** @param array<string, mixed> $changes */
function sellerData(array $changes = []): void
{
    app(Settings::class)->save('invoices', array_replace(['legal_name' => 'Synthetic Seller GmbH', 'street' => 'Teststr. 1', 'postal_code' => '70173', 'city' => 'Stuttgart', 'tax_number' => '99/999/99999', 'vat_id' => 'DE999999999', 'vat_status' => 'registered', 'bank' => 'Test Bank', 'iban' => 'DE00 0000', 'bic' => 'TESTDEXX', 'contact' => 'billing@example.test', 'legal_texts_confirmed' => true], $changes), invUser('admin'));
    app()->forgetScopedInstances();
}

function invCompany(string $status, string $country, ?string $vatId): Company
{
    return app(Companies::class)->create([
        'company' => ['name' => "Customer {$country}", 'kind' => 'customer', 'document_locale' => 'en', 'currency' => 'EUR', 'payment_days' => 14, 'einvoice_format' => 'pdf'],
        'tax' => ['tax_status' => $status, 'country_code' => $country, 'vat_id' => $vatId],
        'address' => ['line1' => 'Weg 2', 'city' => 'Ort', 'postal_code' => '1000', 'country_code' => $country],
    ], test()->controller);
}

/** @param array<string, mixed> $changes */
function freeLine(string $quantity, string $price, string $class = 'standard', array $changes = []): array
{
    return array_replace(['description' => 'Work', 'quantity' => $quantity, 'unit' => 'piece', 'unit_price' => $price, 'rate_class' => $class], $changes);
}

/** @param list<array<string, string>> $lines */
function draft(array $lines, ?Company $company = null): Invoice
{
    return app(Invoices::class)->draft(($company ?? test()->company)->id, '2026-10-01', '2026-10-31', $lines, test()->controller);
}

function issue(Invoice $invoice): Invoice
{
    return app(Invoices::class)->finalize($invoice, test()->controller);
}

/** @return array{0: Project, 1: User} */
function billedProject(): array
{
    $project = Project::query()->create(['number' => 'P-00001', 'name' => 'GG', 'company_id' => test()->company->id, 'status' => 'active', 'billing_model' => 'time_and_material', 'currency' => 'EUR']);
    $employee = invUser('employee');
    ProjectMember::query()->create(['project_id' => $project->id, 'user_id' => $employee->id, 'project_role_id' => ProjectRole::query()->where('key', 'project_member')->firstOrFail()->id]);
    $package = app(WorkBreakdown::class)->add($project, ['name' => 'Consulting', 'billable' => true], null);
    app(Rates::class)->add('tenant', null, '100', 'EUR', '2026-01-01', test()->controller);
    foreach (['2026-10-05' => 20, '2026-10-06' => 20, '2026-10-31' => 20] as $date => $minutes) {
        $entry = app(Timesheets::class)->book($employee, $package->id, $date, $minutes, 'Analysis');
        app(Timesheets::class)->submitWeek($employee, $date);
        app(Timesheets::class)->approve(test()->controller, [$entry->id]);
    }

    return [$project, $employee];
}

it('issues T1 with mixed rates', function () {
    $invoice = issue(draft([freeLine('10', '100'), freeLine('3', '33.33'), freeLine('1', '49.99', 'reduced')]));

    expect([$invoice->number, $invoice->net_minor, $invoice->tax_minor, $invoice->gross_minor])->toBe(['INV-2026-0001', 114998, 21250, 136248])
        ->and($invoice->due_date?->toDateString())->toBe('2026-11-19')
        ->and(array_column($invoice->content()['groups'], 'tax_minor'))->toBe([20900, 350])
        ->and(AuditEntry::query()->where('action', 'invoices.finalized')->sole()->contextData()['number'])->toBe('INV-2026-0001');
});

it('prints the notice and both VAT IDs on reverse charge', function () {
    $invoice = issue(draft([freeLine('40', '100', changes: ['unit' => 'hour'])], invCompany('eu_business', 'AT', 'ATU12345678')));
    $content = $invoice->content();
    expect([$invoice->net_minor, $invoice->tax_minor, $invoice->gross_minor])->toBe([400000, 0, 400000])
        ->and($content['groups'][0]['category'])->toBe('AE')
        ->and($content['notices'][0])->toStartWith('Reverse charge')
        ->and([$content['seller']['vat_id'], $content['buyer']['tax']['vat_id']])->toBe(['DE999999999', 'ATU12345678']);
});

it('issues for a seller in Bosnia and Herzegovina', function () {
    $this->tenant->update(['country_code' => 'BA']);
    $euro = draft([freeLine('1', '1500')], invCompany('domestic', 'BA', '123456789012'));
    expect(app(Invoices::class)->preview($euro)['problems'])->toBe(['tax_currency']);
    $customer = invCompany('domestic', 'BA', null);
    $customer->update(['currency' => 'BAM']);
    expect(app(Invoices::class)->preview(draft([freeLine('1', '1')], $customer))['problems'])->toBe(['customer_id_ba']);
    $customer->taxProfiles()->update(['tax_number' => '4200000000000']);
    $domestic = issue(draft([freeLine('1', '1500'), freeLine('1', '333.33')], $customer));
    $export = issue(draft([freeLine('40', '100')], invCompany('eu_business', 'DE', 'DE123456789')));
    expect([$domestic->tax_minor, $domestic->gross_minor])->toBe([31167, 214500])
        ->and($export->tax_minor)->toBe(0)->and($export->content()['lines'][0]['tax_case'])->toBe('BA_EXPORT_SERVICE_B2B')->and($export->content()['notices'][0])->toContain('outside Bosnia and Herzegovina')->and($export->content()['notices'][1])->toBe('Steuerschuldnerschaft des Leistungsempfängers.');
    sellerData(['vat_id' => null]);
    expect(app(Invoices::class)->preview(draft([freeLine('1', '1')], $customer))['problems'])->toBe(['seller_vat_id_ba']);
});

it('refuses to issue while something is missing and takes no number', function (Closure $make, string $problem) {
    $invoice = $make();
    $this->actingAs($this->controller)->post("/invoices/{$invoice->id}/finalize", ['gross_minor' => 0])->assertSessionHasErrors('invoice');
    expect($invoice->refresh()->number)->toBeNull()->and(app(Invoices::class)->preview($invoice)['problems'])->toContain($problem);
    expect(issue(draft([freeLine('1', '1')]))->number)->toBe('INV-2026-0001');
})->with([
    'no lines' => [fn () => draft([]), 'no_lines'],
    'no VAT ID' => [fn () => draft([freeLine('1', '1')], tap(invCompany('eu_business', 'AT', 'ATU12345678'), fn (Company $c) => $c->taxProfiles()->update(['vat_id' => null]))), 'tax_vat_id_missing'],
    'negative total' => [fn () => draft([freeLine('-1', '100')]), 'negative_total'],
    'service not over' => [fn () => tap(draft([freeLine('1', '1')]), fn (Invoice $i) => $i->update(['service_to' => '2026-12-31'])), 'future_service'],
    'language without texts' => [fn () => tap(draft([freeLine('1', '1')]), fn (Invoice $i) => $i->update(['locale' => 'de'])), 'language'],
    'private outside the EU' => [fn () => draft([freeLine('1', '1')], invCompany('private', 'CH', null)), 'tax_private_outside_eu'],
]);

it('refuses to issue without seller data or confirmed notices', function () {
    $domestic = draft([freeLine('1', '1')]);
    $abroad = draft([freeLine('1', '1')], invCompany('non_eu_business', 'CH', null));
    sellerData(['legal_texts_confirmed' => false]);
    expect(app(Invoices::class)->preview($abroad)['problems'])->toBe(['texts_not_confirmed'])->and(app(Invoices::class)->preview($domestic)['problems'])->toBe([]);
    sellerData(['legal_name' => null, 'tax_number' => null, 'vat_id' => null]);
    expect(app(Invoices::class)->preview($domestic)['problems'])->toBe(['seller_name', 'seller_tax']);
});

it('numbers without gaps and starts again each year', function () {
    $numbers = [issue(draft([freeLine('1', '1')]))->number];
    draft([freeLine('1', '1')]);
    try {
        DB::transaction(function () {
            app(Numbers::class)->next('invoice', 'INV');
            throw new RuntimeException('rendering failed');
        });
    } catch (RuntimeException) {
    }
    $numbers[] = issue(draft([freeLine('1', '1')]))->number;
    // New year in the time zone of the tenant, still the old one in UTC.
    $this->travelTo('2026-12-31 23:30:00');
    $numbers[] = issue(draft([freeLine('1', '1')]))->number;
    expect($numbers)->toBe(['INV-2026-0001', 'INV-2026-0002', 'INV-2027-0001']);
});

it('never takes a number outside a transaction', function () {
    $level = DB::transactionLevel();
    expect($level)->toBeGreaterThan(0);
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    expect(fn () => app(Numbers::class)->next('invoice', 'INV'))->toThrow(LogicException::class);
    DB::beginTransaction();
});

it('never changes or deletes an issued invoice', function () {
    $invoice = issue(draft([freeLine('1', '100')]));
    $line = InvoiceLine::query()->sole();

    $this->actingAs($this->controller)->put("/invoices/{$invoice->id}", ['service_from' => '2026-10-01', 'service_to' => '2026-10-31', 'lines' => [freeLine('2', '1')]])->assertSessionHasErrors('invoice');
    $this->delete("/invoices/{$invoice->id}")->assertSessionHasErrors('invoice');
    expect(fn () => $invoice->update(['gross_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $invoice->refresh()->update(['status' => 'draft']))->toThrow(LogicException::class)
        ->and(fn () => $invoice->refresh()->delete())->toThrow(LogicException::class)
        ->and(fn () => $line->update(['net_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $line->delete())->toThrow(LogicException::class);

    // Changed behind the application: noticed when read.
    DB::table('invoices')->update(['snapshot' => str_replace('11900', '11901', (string) $invoice->refresh()->snapshot)]);
    expect(fn () => Invoice::query()->sole()->content())->toThrow(LogicException::class);
});

it('keeps issued content when customer, seller or texts change', function () {
    $invoice = issue(draft([freeLine('1', '100')]));
    app(Companies::class)->update($this->company, ['company' => ['name' => 'Renamed']], $this->controller);
    sellerData(['legal_name' => 'Other']);
    expect($invoice->refresh()->content()['buyer']['name'])->toBe('Customer DE')->and($invoice->content()['seller']['name'])->toBe('Synthetic Seller GmbH');
});

it('cancels with a document of its own', function () {
    $invoice = issue(draft([freeLine('3', '33.33'), freeLine('1', '49.99', 'reduced')]));
    $this->actingAs($this->controller)->post("/invoices/{$invoice->id}/cancel")->assertSessionHasNoErrors();
    $cancellation = Invoice::query()->where('kind', 'cancellation')->sole();

    expect($cancellation->number)->toBe('CN-2026-0001')->and($cancellation->gross_minor)->toBe(-$invoice->gross_minor)->and($cancellation->tax_minor)->toBe(-$invoice->tax_minor)
        ->and($cancellation->content()['cancels'])->toBe('INV-2026-0001')->and($cancellation->content()['lines'][0]['quantity'])->toBe('-3.0000')
        ->and(array_sum(array_column($cancellation->content()['groups'], 'tax_minor')))->toBe($cancellation->tax_minor)
        ->and($invoice->refresh()->status)->toBe('cancelled')->and($invoice->content()['number'])->toBe('INV-2026-0001');
    $this->post("/invoices/{$invoice->id}/cancel")->assertSessionHasErrors('invoice');
    $this->post("/invoices/{$cancellation->id}/cancel")->assertSessionHasErrors('invoice');
    expect(issue(draft([freeLine('1', '1')]))->number)->toBe('INV-2026-0002');
});

it('bills approved time once, by package and rate', function () {
    [$project, $employee] = billedProject();
    $post = fn (string $from = '2026-10-01', string $to = '2026-10-31'): TestResponse => $this->actingAs($this->controller)->post('/invoices/from-time', ['project_id' => $project->id, 'period_from' => $from, 'period_to' => $to]);
    $post()->assertSessionHasNoErrors();
    $invoice = Invoice::query()->sole();
    $line = InvoiceLine::query()->sole();
    // One hour at 100.00, not three times 33.33.
    expect([$line->minutes, $line->net_minor, $line->description])->toBe([60, 10000, '1 Consulting']);
    $post()->assertSessionHasErrors('period_from');

    // A discarded draft and a cancelled invoice give the time back.
    $this->delete("/invoices/{$invoice->id}")->assertSessionHasNoErrors();
    $post()->assertSessionHasNoErrors();
    $invoice = issue(Invoice::query()->sole());
    $post()->assertSessionHasErrors('period_from');
    app(Invoices::class)->cancel($invoice, $this->controller);
    $post()->assertSessionHasNoErrors();
    expect(InvoiceTime::query()->count())->toBe(6)->and(InvoiceTime::query()->whereNotNull('active_key')->count())->toBe(3);

    // A correction after billing is billed next time.
    $entry = TimeEntry::query()->whereDate('work_date', '2026-10-05')->sole();
    app(Timesheets::class)->correct($this->controller, $entry, 80, 'forgot an hour');
    $post('2026-10-05', '2026-10-05')->assertSessionHasNoErrors();
    expect(InvoiceLine::query()->orderByDesc('created_at')->get()->contains(fn (InvoiceLine $l): bool => $l->minutes === 60 && $l->net_minor === 10000))->toBeTrue();
});

it('does not bill time without a rate, and values it once there is one', function () {
    [$project, $employee] = billedProject();
    DB::table('time_entry_valuations')->delete();
    DB::table('rate_rules')->delete();
    $this->actingAs($this->controller)->post('/invoices/from-time', ['project_id' => $project->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31'])->assertSessionHasErrors('period_from');
    expect(Invoice::query()->count())->toBe(0)->and(InvoiceTime::query()->count())->toBe(0);

    app(Rates::class)->add('tenant', null, '120', 'EUR', '2026-01-01', $this->controller);
    $this->post('/invoices/from-time', ['project_id' => $project->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31'])->assertSessionHasNoErrors();
    expect(InvoiceLine::query()->sole()->net_minor)->toBe(12000);
});

it('bills by time only where time is billed, in the period of the time', function () {
    [$project] = billedProject();
    $data = ['project_id' => $project->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31'];
    $project->update(['billing_model' => 'fixed_price']);
    $this->actingAs($this->controller)->post('/invoices/from-time', $data)->assertSessionHasErrors('project_id');
    $project->update(['billing_model' => 'time_and_material']);
    $this->post('/invoices/from-time', $data)->assertSessionHasNoErrors();
    $this->put('/invoices/'.Invoice::query()->sole()->id, ['service_from' => '2026-11-01', 'service_to' => '2026-11-30'])->assertSessionHasErrors('service_from');
});

it('never issues what the person did not see', function () {
    $invoice = draft([freeLine('1', '100')]);
    app(Invoices::class)->change($invoice, '2026-10-01', '2026-10-31', [freeLine('1', '900')], $this->controller);
    $this->actingAs($this->controller)->post("/invoices/{$invoice->id}/finalize", ['gross_minor' => 11900])->assertSessionHasErrors('invoice');
    $this->post("/invoices/{$invoice->id}/finalize", ['gross_minor' => 107100])->assertSessionHasNoErrors();
    expect($invoice->refresh()->number)->toBe('INV-2026-0001');
});

it('asks for the password before issuing', function () {
    $invoice = draft([freeLine('1', '100')]);
    $this->actingAs($this->controller)->withSession(['auth.password_confirmed_at' => 0])->post("/invoices/{$invoice->id}/finalize", ['gross_minor' => 11900])->assertRedirect('/user/confirm-password');
    expect($invoice->refresh()->number)->toBeNull();
});

it('does not cancel a paid invoice before its payment is reversed', function () {
    $invoice = issue(draft([freeLine('1', '1000')]));
    app(Invoices::class)->pay($invoice, '2026-11-05', 50000, null, $this->controller);
    $this->actingAs($this->controller)->post("/invoices/{$invoice->id}/cancel")->assertSessionHasErrors('invoice');
    app(Invoices::class)->pay($invoice, '2026-11-05', -50000, 'returned', $this->controller);
    $this->post("/invoices/{$invoice->id}/cancel")->assertSessionHasNoErrors();
    expect(fn () => app(Invoices::class)->pay(issue(draft([freeLine('1', '1')])), '2026-11-06', 100, null, $this->controller))->toThrow(ValidationException::class);
});

it('keeps the printed words of issued documents and protects legal notices', function () {
    $invoice = issue(draft([freeLine('1', '100')]));
    expect($invoice->content()['labels']['invoices:document.invoice'])->toBe('Invoice')
        ->and(StringEditor::isProtected('invoices:legal_notice.reverse_charge'))->toBeTrue()
        ->and((new RegionalFormat('en-GB', 'UTC'))->money(123450, 'RSD'))->toContain('1,234.50');
});

it('records payments up to the total', function () {
    Event::fake([InvoicePaid::class]);
    $invoice = issue(draft([freeLine('1', '1000')]));
    $pay = fn (string $amount): TestResponse => $this->actingAs($this->controller)->post("/invoices/{$invoice->id}/payments", ['amount' => $amount, 'paid_on' => '2026-11-05']);
    $status = fn (): mixed => $this->get("/invoices/{$invoice->id}")->inertiaProps('invoice.status');

    $pay('500')->assertSessionHasNoErrors();
    expect($status())->toBe('partially_paid');
    $pay('690,01')->assertSessionHasErrors('amount');
    $pay('0')->assertSessionHasErrors('amount');
    $pay('1,005')->assertSessionHasErrors('amount');
    $pay('-501')->assertSessionHasErrors('amount');
    Event::assertNotDispatched(InvoicePaid::class);
    $pay('690,00')->assertSessionHasNoErrors();
    expect($status())->toBe('paid');
    Event::assertDispatched(InvoicePaid::class, 1);

    $second = issue(draft([freeLine('1', '10')]));
    $this->get('/invoices?show=open')->assertInertia(fn (AssertableInertia $p) => $p->has('invoices.data', 1)->where('invoices.data.0.id', $second->id)->where('invoices.data.0.overdue_days', 0));
    $this->travelTo('2026-11-22 10:00:00');
    $this->flushSession();
    $this->withSession(['auth.password_confirmed_at' => time()]);
    expect($this->actingAs($this->controller)->get('/invoices?show=open')->inertiaProps('invoices.data.0.overdue_days'))->toBe(3);
});

it('keeps the fiscal reference with a logged reason', function () {
    $invoice = issue(draft([freeLine('1', '1')]));
    $put = fn (array $data): TestResponse => $this->actingAs($this->controller)->put("/invoices/{$invoice->id}/fiscal-reference", $data);
    $put(['fiscal_reference' => 'FR-TEST-0001'])->assertSessionHasNoErrors();
    $put(['fiscal_reference' => 'FR-TEST-0002'])->assertSessionHasErrors('reason');
    $put(['fiscal_reference' => 'FR-TEST-0002', 'reason' => 'typing error'])->assertSessionHasNoErrors();
    expect($invoice->refresh()->fiscal_reference)->toBe('FR-TEST-0002')->and(AuditEntry::query()->where('action', 'invoices.fiscal_reference')->count())->toBe(2);
});

it('renders the PDF of issued documents only', function () {
    $invoice = draft([freeLine('1.5', '33.3333'), freeLine('1', '49.99', 'reduced')]);
    $this->actingAs($this->controller)->get("/invoices/{$invoice->id}/pdf")->assertNotFound();
    issue($invoice);
    $pdf = $this->get("/invoices/{$invoice->id}/pdf")->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')->and($pdf->getContent())->toStartWith('%PDF-')->not->toContain('mPDF')
        ->and($pdf->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('refuses wrong drafts', function (array $line, string $field) {
    $this->actingAs($this->controller)->post('/invoices', ['company_id' => $this->company->id, 'service_from' => '2026-10-01', 'service_to' => '2026-10-31', 'lines' => [freeLine('1', '1', changes: $line)]])->assertSessionHasErrors($field);
})->with([[['quantity' => '1e3'], 'lines.0.quantity'], [['unit_price' => 'NaN'], 'lines.0.unit_price'], [['rate_class' => 'zero'], 'lines.0.rate_class'], [['net_minor' => '1'], 'lines.0']]);

it('is for finance roles, in their tenant', function () {
    $invoice = issue(draft([freeLine('1', '1')]));
    foreach (['employee', 'project_lead'] as $role) {
        $this->actingAs(invUser($role))->get('/invoices')->assertForbidden();
        $this->get("/invoices/{$invoice->id}/pdf")->assertForbidden();
        $this->post("/invoices/{$invoice->id}/cancel")->assertForbidden();
    }
    $other = Tenant::factory()->create();
    app(TenantContext::class)->run($other, function () use ($invoice) {
        expect(Invoice::query()->find($invoice->id))->toBeNull()->and(fn () => app(Invoices::class)->finalize($invoice, $this->controller))->toThrow(ModelNotFoundException::class);
        // Own numbers per tenant.
        expect(DB::transaction(fn () => app(Numbers::class)->next('invoice', 'INV')))->toBe('INV-2026-0001');
    });
});

it('generates a service report that never changes', function () {
    [$project, $employee] = billedProject();
    $post = fn (array $changes = []): TestResponse => $this->post('/service-reports', array_replace(['project_id' => $project->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'with_amounts' => true], $changes));
    $this->actingAs($this->controller);
    $post()->assertSessionHasNoErrors();
    $report = ServiceReport::query()->sole();
    $content = $report->content();

    expect($report->number)->toBe('SR-2026-0001')->and($content['minutes'])->toBe(60)->and($content['amounts'])->toBe(['EUR' => 10000])
        ->and(array_column($content['lines'], 'date'))->toBe(['2026-10-05', '2026-10-06', '2026-10-31'])
        ->and($content['totals'])->toEqual(['package' => ['1 Consulting' => 60], 'user' => [$employee->name => 60]])
        ->and(fn () => $report->update(['snapshot' => '{}']))->toThrow(LogicException::class)
        ->and(fn () => $report->refresh()->delete())->toThrow(LogicException::class);

    app(Timesheets::class)->correct($this->controller, TimeEntry::query()->firstOrFail(), 0, 'x');
    expect($report->refresh()->content()['minutes'])->toBe(60);
    $post(['period_from' => '2026-09-01', 'period_to' => '2026-09-30'])->assertSessionHasErrors('period_from');

    expect($this->get("/service-reports/{$report->id}/pdf")->assertOk()->getContent())->toStartWith('%PDF-')
        ->and($this->get("/service-reports/{$report->id}/csv")->assertOk()->getContent())->toContain('"33.33"');
});

it('lets project leads report hours of their project, never amounts', function () {
    [$project] = billedProject();
    $lead = invUser('project_lead');
    $this->actingAs($lead)->get('/service-reports')->assertForbidden();
    ProjectMember::query()->create(['project_id' => $project->id, 'user_id' => $lead->id, 'project_role_id' => ProjectRole::query()->where('key', 'project_lead')->firstOrFail()->id]);
    app()->forgetScopedInstances();
    $data = ['project_id' => $project->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31'];

    $this->post('/service-reports', [...$data, 'with_amounts' => true])->assertForbidden();
    $this->post('/service-reports', $data)->assertSessionHasNoErrors();
    $content = ServiceReport::query()->sole()->content();
    expect($content['amounts'])->toBe([])->and(array_keys($content['lines'][0]))->toEqualCanonicalizing(['date', 'user', 'package', 'minutes', 'description', 'correction']);
    $mine = ServiceReport::query()->sole();
    $this->get("/service-reports/{$mine->id}/csv")->assertOk();

    $this->actingAs($this->controller)->post('/service-reports', [...$data, 'with_amounts' => true])->assertSessionHasNoErrors();
    $priced = ServiceReport::query()->where('with_amounts', true)->sole();
    $this->actingAs($lead)->get("/service-reports/{$priced->id}/pdf")->assertNotFound();
    $this->get('/service-reports')->assertInertia(fn (AssertableInertia $p) => $p->has('reports', 1));
});

it('mails issued invoices to known contacts only', function () {
    $sent = [];
    Event::listen(MessageSending::class, function ($e) use (&$sent) {
        $sent[] = $e->message;
    });
    app(Companies::class)->update($this->company, ['company' => [], 'contacts' => [['last_name' => 'Billing', 'email' => 'billing@customer.test', 'invoice_recipient' => true], ['last_name' => 'Other', 'email' => 'other@customer.test', 'invoice_recipient' => false]]], $this->controller);
    $invoice = draft([freeLine('1', '100')]);
    $post = fn (array $to): TestResponse => $this->actingAs($this->controller)->post("/invoices/{$invoice->id}/send", ['recipients' => $to]);
    $post(['billing@customer.test'])->assertUnprocessable();
    issue($invoice);
    $post(['other@customer.test'])->assertSessionHasErrors('recipients.0');
    $post(['attacker@example.test'])->assertSessionHasErrors('recipients.0');
    expect(InvoiceDelivery::query()->count())->toBe(0);
    // Through the real queue: its worker starts without any tenant.
    config(['queue.default' => 'database']);
    $post(['billing@customer.test'])->assertSessionHasNoErrors();
    expect(InvoiceDelivery::query()->sole()->status)->toBe('queued');
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);
    app(TenantContext::class)->set($this->tenant);
    expect(InvoiceDelivery::query()->sole()->status)->toBe('sent')->and($sent)->toHaveCount(1)->and($sent[0]->getSubject())->toBe('Invoice INV-2026-0001')->and($sent[0]->getAttachments()[0]->getFilename())->toBe('INV-2026-0001.pdf')->and($sent[0]->getTo()[0]->getAddress())->toBe('billing@customer.test')->and(AuditEntry::query()->where('action', 'invoices.sent')->count())->toBe(1);
});

it('exports the report as a workbook with formulas', function () {
    [$project, $employee] = billedProject();
    $entry = app(Timesheets::class)->book($employee, TimeEntry::query()->firstOrFail()->work_package_id, '2026-10-12', 30, '=1+1');
    app(Timesheets::class)->submitWeek($employee, '2026-10-12');
    app(Timesheets::class)->approve($this->controller, [$entry->id]);
    $this->actingAs($this->controller)->post('/service-reports', ['project_id' => $project->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'with_amounts' => true]);
    $file = tempnam(sys_get_temp_dir(), 'x');
    file_put_contents($file, $this->get('/service-reports/'.ServiceReport::query()->sole()->id.'/xlsx')->assertOk()->getContent());
    $sheet = IOFactory::load($file)->getActiveSheet();
    unlink($file);
    $texts = array_map(fn (int $r): mixed => $sheet->getCell("D{$r}")->getDataType().':'.$sheet->getCell("D{$r}")->getValue(), [2, 3, 4, 5]);
    expect($texts)->toContain('s:=1+1')
        ->and($sheet->getCell('E6')->getValue())->toBe('=SUM(E2:E5)')->and(round($sheet->getCell('E6')->getCalculatedValue() * 1440))->toBe(90.0)
        ->and($sheet->getCell('G6')->getValue())->toBe('=SUM(G2:G5)')->and(round($sheet->getCell('G6')->getCalculatedValue(), 2))->toBe(149.99);
});

it('keeps formulas out of the CSV', function () {
    [$project, $employee] = billedProject();
    $entry = app(Timesheets::class)->book($employee, TimeEntry::query()->firstOrFail()->work_package_id, '2026-10-12', 30, '=HYPERLINK("x")');
    app(Timesheets::class)->submitWeek($employee, '2026-10-12');
    app(Timesheets::class)->approve($this->controller, [$entry->id]);
    $this->actingAs($this->controller)->post('/service-reports', ['project_id' => $project->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31']);
    expect($this->get('/service-reports/'.ServiceReport::query()->sole()->id.'/csv')->getContent())->toContain('"\'=HYPERLINK(""x"")"');
});

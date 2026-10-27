<?php

use App\Enums\Permission;
use App\Livewire\MonthEnd\MonthEndReport;
use App\Livewire\Overview;
use App\Models\AppSetting;
use App\Models\BillingPeriod;
use App\Models\MonthlyCost;
use App\Models\MonthlyProjection;
use App\Models\Project;
use App\Models\StaffCostRate;
use App\Models\StaffMonthHours;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Two billed months of 2026 (May 10k, June 6k), costs for May and July only,
 * and 9k paid against the 16k billed.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    Project::create(['job_number' => '#1', 'job_name' => 'One', 'developer' => 'Acme', 'division' => 'MF', 'active' => true]);
    Project::create(['job_number' => '#2', 'job_name' => 'Two', 'developer' => 'Bolt', 'division' => 'REN', 'active' => true]);

    BillingPeriod::create(['period' => '2026-05-31', 'status' => 'closed']);
    BillingPeriod::create(['period' => '2026-06-30', 'status' => 'open']);

    DB::table('month_hourly')->insert([
        ['period' => '2026-05-31', 'job_number' => '#1', 'amount' => 10000],
        ['period' => '2026-06-30', 'job_number' => '#2', 'amount' => 6000],
    ]);

    DB::table('payments')->insert([
        ['paid_on' => '2026-06-20', 'job_number' => '#1', 'amount' => 9000],
    ]);

    MonthlyCost::create(['month' => '2026-05', 'category' => 'Payroll', 'amount' => 6000, 'seq' => 1]);
    MonthlyCost::create(['month' => '2026-05', 'category' => 'Rent', 'amount' => 1000, 'seq' => 2]);
    MonthlyCost::create(['month' => '2026-05', 'category' => 'Software', 'amount' => 500, 'seq' => 3]);
    MonthlyCost::create(['month' => '2026-07', 'category' => 'Payroll', 'amount' => 9000, 'seq' => 1]);
});

it('works out profit only over months that have both billing and cost', function () {
    $figures = Livewire::test(Overview::class, ['year' => 2026, 'span' => 'year'])->instance()->figures;

    expect($figures)->toMatchArray([
        'ytd' => 16000.0,
        'cost' => 7500.0,
        'payroll' => 6000.0,
        'overhead' => 1500.0,
        'costedMonths' => 1,
        'profit' => 2500.0,
        'margin' => 0.25,
        'uncosted' => ['Jun'],
        'costsWaiting' => ['Jul'],
    ]);
});

it('opens on the latest billed quarter and switches quarter or to the whole year', function () {
    $component = Livewire::test(Overview::class, ['year' => 2026])
        ->assertSet('span', 'quarter')
        ->assertSet('quarter', 2)
        ->assertSee('Q2 2026')
        ->assertSee('quarter to date');

    expect($component->instance()->figures)->toMatchArray(['ytd' => 16000.0, 'spanMonths' => 3, 'costsWaiting' => []])
        ->and($component->instance()->figures['chart']['labels'])->toBe(['Apr', 'May', 'Jun']);

    $component->call('setQuarter', 3);
    expect($component->instance()->figures)->toMatchArray(['ytd' => 0.0, 'costsWaiting' => ['Jul']]);

    $component->call('setSpan', 'year')->assertSee('year to date');
    expect($component->instance()->figures)->toMatchArray(['ytd' => 16000.0, 'spanMonths' => 12]);
});

it('counts what is billed and not yet paid on active jobs as owed, up to the latest payment', function () {
    Project::create(['job_number' => '#3', 'job_name' => 'Closed', 'developer' => 'Cove', 'division' => 'MF', 'active' => false]);
    DB::table('month_hourly')->insert(['period' => '2026-05-31', 'job_number' => '#3', 'amount' => 4000]);

    DB::table('payments')->insert(['paid_on' => '2026-07-10', 'job_number' => '#2', 'amount' => 2000]);

    $figures = Livewire::test(Overview::class, ['year' => 2026])->instance()->figures;
    $owed = $figures['owed'];

    expect($owed['total'])->toBe(5000.0)
        ->and($owed['jobs'])->toBe(2)
        ->and($figures['owedAsAt'])->toBe('2026-07-10')
        ->and(collect($owed['clients'])->pluck('owed', 'client')->all())->toBe(['Bolt' => 4000.0, 'Acme' => 1000.0]);
});

it('shows no profit until costs are entered', function () {
    MonthlyCost::query()->delete();

    $figures = Livewire::test(Overview::class, ['year' => 2026])
        ->assertSee('add a Costs sheet to the workbook')
        ->instance()->figures;

    expect($figures['profit'])->toBeNull()
        ->and($figures['margin'])->toBeNull();
});

it('shows billed, owed, cost and profit on the page', function () {
    $this->get(route('overview', ['year' => 2026]))
        ->assertOk()
        ->assertSeeInOrder(['Billed', 'Owed to us', 'Cost', 'Profit'])
        ->assertSee('No costs entered for Jun');
});

it('estimates cost from staff time for months the Costs sheet leaves out', function () {
    AppSetting::create(['key' => 'mosaic.hours_through', 'value' => '2026-06']);
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'hourly_cost' => 40, 'starts_on' => '2026-01-01']);
    StaffMonthHours::create(['email' => 'pat@formwerks.ca', 'month' => '2026-06', 'hours' => 100, 'source' => 'mosaic']);
    StaffMonthHours::create(['email' => 'sam@formwerks.ca', 'month' => '2026-06', 'hours' => 10, 'source' => 'mosaic']);

    $figures = Livewire::test(Overview::class, ['year' => 2026])
        ->assertSee('Profit (estimate)')
        ->instance()->figures;

    expect($figures)->toMatchArray([
        'cost' => 11500.0,
        'costedMonths' => 2,
        'profit' => 4500.0,
        'estimatedMonths' => ['Jun'],
        'estimatedUncostedHours' => 10.0,
        'uncosted' => [],
    ]);
});

it('leaves staff time out of cost for those who do not manage staff costs', function () {
    $user = User::factory()->staff()->create();
    $user->givePermissionTo(Permission::ViewFinancials->value);
    $this->actingAs($user);

    AppSetting::create(['key' => 'mosaic.hours_through', 'value' => '2026-06']);
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'hourly_cost' => 40, 'starts_on' => '2026-01-01']);
    StaffMonthHours::create(['email' => 'pat@formwerks.ca', 'month' => '2026-06', 'hours' => 100, 'source' => 'mosaic']);

    expect(Livewire::test(Overview::class, ['year' => 2026])->instance()->figures)
        ->toMatchArray(['cost' => 7500.0, 'estimatedMonths' => [], 'uncosted' => ['Jun']]);
});

it('shows each month\'s profit on Month End', function () {
    $june = Livewire::test(MonthEndReport::class, ['period' => '2026-06-30'])->get('figures')['spent'];

    MonthlyCost::create(['month' => '2026-06', 'category' => 'Payroll', 'amount' => 4500]);
    $withSheet = Livewire::test(MonthEndReport::class, ['period' => '2026-06-30'])
        ->assertSee('Profit')
        ->get('figures')['spent'];

    expect($june)->toMatchArray(['key' => '2026-06', 'total' => null, 'profit' => null])
        ->and($withSheet)->toMatchArray(['basis' => 'sheet', 'total' => 4500.0, 'profit' => 1500.0, 'margin' => 0.25]);
});

it('takes projections for the quarter and sets them against what came in', function () {
    $component = Livewire::test(Overview::class, ['year' => 2026, 'quarter' => 2])
        ->assertSee('Projections')
        ->call('saveProjection', '2026-05', 'billed', '$12,000')
        ->call('saveProjection', '2026-05', 'cost', '7,000')
        ->call('saveProjection', '2026-06', 'billed', 'lots')
        ->assertHasErrors('projection');

    expect(MonthlyProjection::sole()->only(['month', 'billed', 'cost']))->toBe(['month' => '2026-05', 'billed' => '12000.00', 'cost' => '7000.00']);

    $figures = $component->instance()->figures;
    expect($figures['chart']['projected'])->toBe([null, 12000.0, null])
        ->and($figures['projections'])->toMatchArray(['billed' => 12000.0, 'cost' => 7000.0]);

    $component->assertSee('−$2,000');

    $component->call('saveProjection', '2026-05', 'billed', '')->call('saveProjection', '2026-05', 'cost', '');
    expect(MonthlyProjection::count())->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['table_name' => 'monthly_projections', 'action' => 'delete']);
});

it('needs edit billing to set a projection', function () {
    $viewer = User::factory()->staff()->create();
    $viewer->givePermissionTo(Permission::ViewFinancials->value);

    Livewire::actingAs($viewer)
        ->test(Overview::class, ['year' => 2026])
        ->call('saveProjection', '2026-05', 'billed', '100')
        ->assertForbidden();
});

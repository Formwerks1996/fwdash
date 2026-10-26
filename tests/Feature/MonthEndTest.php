<?php

use App\Actions\Billing\AddConsultantFeeAction;
use App\Actions\Billing\SaveHourLineAction;
use App\Actions\Billing\SetHourlyOverrideAction;
use App\Actions\Billing\SetPeriodStatusAction;
use App\Billing\BillingEngine;
use App\Enums\Permission;
use App\Livewire\MonthEnd\MonthEndEntry;
use App\Livewire\MonthEnd\MonthEndReport;
use App\Models\BillingPeriod;
use App\Models\ConsultantFee;
use App\Models\HourLine;
use App\Models\MonthEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * A June built so its totals mirror the shape of the real one: phase draws by
 * percentage and by typed amount, hourly lines, an override that caps them and
 * a flat hourly figure with no lines at all.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    Project::create(['job_number' => '#1', 'job_name' => 'Alder', 'developer' => 'Acme', 'division' => 'MF', 'active' => true, 'ca_per_month' => 1500]);
    Project::create(['job_number' => '#2', 'job_name' => 'Birch', 'developer' => 'Bolt', 'division' => 'REN', 'active' => true]);
    Project::create(['job_number' => '#3', 'job_name' => 'Cedar', 'developer' => 'Acme', 'division' => 'MF', 'active' => true]);
    Project::create(['job_number' => '#4', 'job_name' => 'Dogwood', 'division' => 'SF', 'active' => false]);

    DB::table('project_phase_fees')->insert([
        ['job_number' => '#1', 'phase_code' => 'SD', 'fee' => 48378.75, 'note' => 'as signed'],
        ['job_number' => '#1', 'phase_code' => 'CA', 'fee' => 20000, 'note' => null],
        ['job_number' => '#2', 'phase_code' => 'DD to DP', 'fee' => 30000, 'note' => null],
    ]);

    DB::table('rate_titles')->insert(['title' => 'Architect', 'default_charge_out' => 150, 'seq' => 1]);
    DB::table('staff')->insert([
        ['name' => 'Adam', 'rate_title' => 'Architect', 'charge_out' => 160, 'current' => true],
        ['name' => 'Beth', 'rate_title' => 'Architect', 'charge_out' => null, 'current' => true],
        ['name' => 'Cleo', 'rate_title' => null, 'charge_out' => null, 'current' => true],
    ]);

    foreach (['2026-04-30', '2026-05-31', '2026-06-30'] as $period) {
        BillingPeriod::create(['period' => $period, 'status' => 'open']);
    }

    DB::table('month_entries')->insert([
        ['period' => '2026-05-31', 'job_number' => '#1', 'phase_code' => 'SD', 'pct_complete' => 0.25, 'amount' => null, 'fee_at_entry' => 48378.75, 'note' => null],
        ['period' => '2026-06-30', 'job_number' => '#1', 'phase_code' => 'SD', 'pct_complete' => 0.5, 'amount' => null, 'fee_at_entry' => 48378.75, 'note' => 'invoice 1042'],
        ['period' => '2026-06-30', 'job_number' => '#1', 'phase_code' => 'CA', 'pct_complete' => null, 'amount' => 1500, 'fee_at_entry' => 20000, 'note' => null],
        ['period' => '2026-06-30', 'job_number' => '#2', 'phase_code' => 'DD to DP', 'pct_complete' => 0.1, 'amount' => null, 'fee_at_entry' => 30000, 'note' => null],
    ]);

    DB::table('hour_lines')->insert([
        ['period' => '2026-06-30', 'job_number' => '#2', 'staff' => 'Adam', 'hours' => 10, 'rate' => 160, 'amount' => null, 'rate_source' => 'imported'],
        ['period' => '2026-06-30', 'job_number' => '#2', 'staff' => 'Beth', 'hours' => 4.5, 'rate' => 150, 'amount' => null, 'rate_source' => 'imported'],
    ]);

    DB::table('month_hourly')->insert([
        ['period' => '2026-06-30', 'job_number' => '#2', 'amount' => 2000],
        ['period' => '2026-06-30', 'job_number' => '#3', 'amount' => 24189.375],
    ]);

    DB::table('job_hours')->insert([
        ['period' => '2026-06-30', 'job_number' => '#1', 'hours' => 40, 'source' => 'live'],
        ['period' => '2026-06-30', 'job_number' => '#4', 'hours' => 12.5, 'source' => 'live'],
        ['period' => '2022-04-30', 'job_number' => '#1', 'hours' => 100, 'source' => 'prior'],
    ]);

    DB::table('payments')->insert(['paid_on' => '2026-03-15', 'job_number' => '#1', 'amount' => 5000, 'source' => 'import']);
});

it('reports the month the way the Monthly Billing Report totals it', function () {
    $report = app(BillingEngine::class)->periodReport('2026-06-30');

    // #1: 24189.38 (SD) + 1500 (CA draw); #2: 3000 phase + 2000 capped hourly; #3: 24189.38 flat hourly.
    expect($report['jobCount'])->toBe(3)
        ->and(array_column($report['jobs'], 'jobNumber'))->toBe(['#1', '#2', '#3'])
        ->and($report['totalThisMonth'])->toBe('54878.76')
        ->and($report['totalExtras'])->toBe('26189.38')
        ->and($report['totalPhaseBilling'])->toBe('28689.38')
        ->and($report['hoursThisMonth'])->toBe(52.5);

    $grid = $report['jobs'][0];
    $sd = collect($grid['phases'])->firstWhere('phase', 'SD');

    expect($grid['phases'])->toHaveCount(11)
        ->and($sd['billedBefore'])->toBe('12094.69')
        ->and((float) $sd['completionBefore'])->toEqualWithDelta(0.25, 0.0001)
        ->and($sd['billedThisMonth'])->toBe('24189.38')
        ->and($sd['note'])->toBe('invoice 1042');

    foreach ($report['jobs'] as $block) {
        $phases = array_sum(array_map(fn (array $row) => (float) $row['billedThisMonth'], $block['phases']));

        expect(round($phases + (float) $block['totals']['hourlyBilled'], 2))->toBe((float) $block['totals']['billed']);
    }
});

it('lists the jobs to work through with the reason each is there', function () {
    DB::table('month_entries')->where('job_number', '#1')->where('period', '2026-06-30')->delete();

    $worklist = app(BillingEngine::class)->worklist('2026-06-30');
    $jobs = collect($worklist['jobs'])->keyBy('jobNumber');

    // #3 bills only through a flat hourly figure: no lines, hours or entries put it on the list.
    expect($jobs->keys()->all())->toBe(['#1', '#2', '#4'])
        ->and($jobs['#1']['reasons'])->toBe(['hours logged', 'CA draw due'])
        ->and($jobs['#1']['done'])->toBeFalse()
        ->and($jobs['#1']['hoursLogged'])->toBe(40.0)
        ->and($jobs['#2']['reasons'])->toBe(['already entered'])
        ->and($jobs['#4']['reasons'])->toBe(['hours logged'])
        ->and($worklist['outstandingCount'])->toBe(2);
});

it('shows the report, the entry screen and both printed versions', function () {
    $this->get(route('monthEnd'))->assertOk()->assertSee('Month end')->assertSee('Alder')->assertSee('Under $95/hr');
    $this->get(route('monthEnd.entry', ['period' => '2026-06-30', 'job' => '#1']))->assertOk()->assertDontSee('Pick a job to bill it.')->assertSee('invoice 1042');
    $this->get(route('monthEnd.print', ['period' => '2026-06-30']))->assertOk()->assertSee('MF Monthly Contract Status - June 2026')->assertSee('Print / Save as PDF');
    $this->get(route('monthEnd.print', ['period' => '2026-06-30', 'variant' => 'classic']))->assertOk()->assertSee('Grand Total')->assertSee('Balance Design');
    $this->get(route('monthEnd.print', ['period' => '2031-01-31']))->assertNotFound();
});

it('keeps month end away from staff without the financials permission', function () {
    $this->actingAs(User::factory()->staff()->create());

    $this->get(route('monthEnd'))->assertForbidden();
    $this->get(route('monthEnd.entry'))->assertForbidden();
    $this->get(route('monthEnd.print', ['period' => '2026-06-30']))->assertForbidden();
});

it('lets a viewer read month end but not write to it', function () {
    $viewer = User::factory()->staff()->create();
    $viewer->givePermissionTo(Permission::ViewFinancials->value);

    $this->actingAs($viewer);

    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])
        ->call('openJob', '#2')
        ->call('savePct', 'DD to DP', '20')
        ->assertForbidden();

    Livewire::test(MonthEndReport::class)->call('closePeriod')->assertForbidden();
});

it('keeps an entry\'s note when its percentage is saved', function () {
    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])
        ->call('openJob', '#1')
        ->call('savePct', 'SD', '12.5')
        ->assertHasNoErrors()
        ->assertSet('flash', 'SD 12.5%');

    $entry = MonthEntry::where(['period' => '2026-06-30', 'job_number' => '#1', 'phase_code' => 'SD'])->first();

    expect((float) $entry->pct_complete)->toBe(0.125)
        ->and($entry->note)->toBe('invoice 1042');
});

it('clears a percentage when an amount is typed for the phase', function () {
    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])
        ->call('openJob', '#2')
        ->call('saveAmount', 'DD to DP', '4,000');

    $entry = MonthEntry::where(['period' => '2026-06-30', 'job_number' => '#2', 'phase_code' => 'DD to DP'])->first();

    expect($entry->pct_complete)->toBeNull()
        ->and((float) $entry->amount)->toBe(4000.0);
});

it('defaults an hour line\'s rate from the rate table and records where it came from', function () {
    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])
        ->call('openJob', '#1')
        ->set('newLine.staff', 'Beth')
        ->set('newLine.hours', '3')
        ->call('addLine')
        ->assertHasNoErrors()
        ->assertSet('flash', 'Beth added');

    $line = HourLine::where('job_number', '#1')->first();

    expect((float) $line->rate)->toBe(150.0)
        ->and($line->rate_source)->toBe('rate title default');

    $this->assertDatabaseHas('audit_logs', ['action' => 'insert', 'table_name' => 'hour_lines', 'record_key' => (string) $line->id]);
});

it('refuses a line nobody can price', function () {
    app(SaveHourLineAction::class)->execute('2026-06-30', '#1', ['staff' => 'Cleo', 'hours' => '2']);
})->throws(ValidationException::class, 'No rate for Cleo on #1 (unresolved)');

it('removes a line and puts it back whole', function () {
    $line = HourLine::create(['period' => '2026-06-30', 'job_number' => '#1', 'staff' => 'Adam', 'hours' => null, 'rate' => null, 'amount' => 750, 'rate_source' => 'imported', 'note' => 'flat fee']);

    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])
        ->call('openJob', '#1')
        ->call('deleteLine', $line->id)
        ->assertSet('undo.amount', '750.00')
        ->call('undoLine')
        ->assertSet('undo', null);

    $restored = HourLine::find($line->id);

    expect((float) $restored->amount)->toBe(750.0)
        ->and($restored->hours)->toBeNull()
        ->and($restored->rate_source)->toBe('imported')
        ->and($restored->note)->toBe('flat fee');
});

it('sets and clears a flat hourly figure', function () {
    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])
        ->call('openJob', '#2')
        ->set('flatAmount', '2500')
        ->call('setOverride')
        ->assertSet('flash', 'flat hourly amount set');

    expect(app(BillingEngine::class)->hourlyBilled('#2', '2026-06-30'))->toBe('2500.00');

    app(SetHourlyOverrideAction::class)->execute('2026-06-30', '#2', null);

    $this->assertDatabaseMissing('month_hourly', ['period' => '2026-06-30', 'job_number' => '#2']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'delete', 'table_name' => 'month_hourly', 'record_key' => '2026-06-30|#2']);
});

it('refuses an override on an unknown job', function () {
    app(SetHourlyOverrideAction::class)->execute('2026-06-30', '#99', '100');
})->throws(ValidationException::class, 'Unknown job #99.');

it('closes a month against entries, lines and overrides, then reopens it', function () {
    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])->call('closePeriod');

    $period = BillingPeriod::find('2026-06-30');

    expect($period->status)->toBe('closed')
        ->and($period->closed_by)->toBe(auth()->user()->name);

    $this->assertDatabaseHas('audit_logs', ['action' => 'close', 'table_name' => 'billing_periods', 'record_key' => '2026-06-30']);

    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])
        ->call('openJob', '#2')
        ->call('savePct', 'DD to DP', '20')
        ->assertHasErrors('entry');

    expect(fn () => app(SaveHourLineAction::class)->execute('2026-06-30', '#2', ['staff' => 'Adam', 'hours' => '1']))
        ->toThrow(ValidationException::class, 'Billing period 2026-06-30 is closed.')
        ->and(fn () => app(SetHourlyOverrideAction::class)->execute('2026-06-30', '#2', '10'))
        ->toThrow(ValidationException::class, 'Billing period 2026-06-30 is closed.');

    app(AddConsultantFeeAction::class)->execute('2026-06-30', '#2', 'SKETS', null, '800');

    app(SetPeriodStatusAction::class)->execute('2026-06-30', 'open');

    expect(BillingPeriod::find('2026-06-30')->closed_at)->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'reopen', 'table_name' => 'billing_periods']);
});

it('prices a consultant fee from the latest rate at its exchange rate, unrounded', function () {
    DB::table('consultant_rates')->insert([
        ['period' => '2026-01-31', 'consultant' => 'SKETS', 'rate' => 20, 'exchange_rate' => null],
        ['period' => '2026-05-31', 'consultant' => 'SKETS', 'rate' => 22, 'exchange_rate' => 1.372345],
        ['period' => '2026-07-31', 'consultant' => 'SKETS', 'rate' => 30, 'exchange_rate' => null],
    ]);

    Livewire::test(MonthEndEntry::class, ['period' => '2026-06-30'])
        ->call('openJob', '#1')
        ->set('newFee.consultant', 'SKETS')
        ->set('newFee.hours', '10.5')
        ->call('addConsultantFee')
        ->assertHasNoErrors();

    expect((float) ConsultantFee::first()->total)->toEqualWithDelta(10.5 * 22 * 1.372345, 0.000001);
});

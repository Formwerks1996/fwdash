<?php

use App\Actions\Billing\SaveMonthEntryAction;
use App\Actions\Billing\SetPhaseFeeAction;
use App\Billing\BillingEngine;
use App\Models\BillingPeriod;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    Project::create(['job_number' => '#1', 'job_name' => 'One', 'active' => true]);

    DB::table('project_phase_fees')->insert([
        ['job_number' => '#1', 'phase_code' => 'SD', 'fee' => 24189.375 * 2, 'note' => null],
        ['job_number' => '#1', 'phase_code' => 'CA', 'fee' => 10000, 'note' => null],
    ]);
});

it('rounds half away from zero like Excel', function () {
    expect(Money::round(24189.375))->toBe('24189.38')
        ->and(Money::round(-2.345))->toBe('-2.35')
        ->and(Money::round('0.125'))->toBe('0.13');
});

it('bills a percentage of the fee as it stood when the month was billed', function () {
    app(SaveMonthEntryAction::class)->execute('2026-05-31', '#1', 'SD', pct: '0.5', amount: null);

    app(SetPhaseFeeAction::class)->execute(Project::find('#1'), 'SD', '1000');

    $engine = app(BillingEngine::class);
    $engine->load();

    expect($engine->jobSummary('#1')['billed'])->toBe('24189.38')
        ->and($engine->jobFeeDetail('#1')[1]['billedAtOtherFee'])->toBe(1);
});

it('lets a typed amount win and an hourly override replace the lines', function () {
    DB::table('month_entries')->insert(['period' => '2026-05-31', 'job_number' => '#1', 'phase_code' => 'CA', 'pct_complete' => null, 'amount' => 1500, 'fee_at_entry' => 10000]);
    DB::table('hour_lines')->insert(['period' => '2026-05-31', 'job_number' => '#1', 'staff' => 'Adam', 'hours' => 10, 'rate' => 145]);
    DB::table('month_hourly')->insert(['period' => '2026-05-31', 'job_number' => '#1', 'amount' => 1000]);

    $totals = app(BillingEngine::class)->jobPeriodTotals('#1', '2026-05-31');

    expect($totals)->toBe(['phaseBilled' => '1500.00', 'hourlyBilled' => '1000.00', 'billed' => '2500.00']);
});

it('refuses writes to a closed period', function () {
    BillingPeriod::create(['period' => '2026-04-30', 'status' => 'closed']);

    app(SaveMonthEntryAction::class)->execute('2026-04-30', '#1', 'SD', pct: '0.1', amount: null);
})->throws(ValidationException::class, 'Billing period 2026-04-30 is closed.');

it('logs every write with who made it', function () {
    app(SaveMonthEntryAction::class)->execute('2026-05-31', '#1', 'SD', pct: '0.1', amount: null);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'insert',
        'table_name' => 'month_entries',
        'record_key' => '2026-05-31|#1|SD',
        'user_id' => auth()->id(),
    ]);
});

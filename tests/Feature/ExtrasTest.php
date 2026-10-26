<?php

use App\Billing\BillingEngine;
use App\Models\BillingPeriod;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Project::create(['job_number' => '#1', 'job_name' => 'One', 'developer' => 'Acme', 'active' => true]);
    Project::create(['job_number' => '#2', 'job_name' => 'Two', 'active' => true]);

    BillingPeriod::create(['period' => '2026-05-31', 'status' => 'closed']);
    BillingPeriod::create(['period' => '2026-06-30', 'status' => 'open']);

    DB::table('hour_lines')->insert([
        ['period' => '2026-06-30', 'job_number' => '#1', 'staff' => 'Adam', 'hours' => 10, 'rate' => 150, 'amount' => null],
        ['period' => '2026-06-30', 'job_number' => '#1', 'staff' => 'Beth', 'hours' => 2, 'rate' => 120, 'amount' => 0],
        ['period' => '2026-06-30', 'job_number' => '#1', 'staff' => 'Beth', 'hours' => null, 'rate' => null, 'amount' => 300],
    ]);

    DB::table('month_hourly')->insert([
        ['period' => '2026-06-30', 'job_number' => '#1', 'amount' => 1400],
        ['period' => '2026-06-30', 'job_number' => '#2', 'amount' => 500],
    ]);
});

it('rates the lines with hours over those hours and keeps written-off time in the hours', function () {
    $extras = app(BillingEngine::class)->extras();

    $june = collect($extras['months'])->firstWhere('period', '2026-06-30');
    $cells = collect($extras['jobMonths'])->keyBy('jobNumber');

    expect($cells['#1'])->toMatchArray(['kind' => 'adjusted', 'hours' => 12.0, 'billed' => '1400.00', 'linesTotal' => '1800.00', 'rate' => 125.0, 'writtenOffHours' => 2.0])
        ->and($cells['#2'])->toMatchArray(['kind' => 'flat', 'hours' => 0.0, 'billed' => '500.00', 'rate' => null])
        ->and($june)->toMatchArray([
            'hours' => 12.0,
            'billed' => '1900.00',
            'linesTotal' => '1800.00',
            'flat' => '500.00',
            'adjust' => '-400.00',
            'rate' => 125.0,
            'writtenOffHours' => 2.0,
            'jobs' => 2,
            'people' => 2,
        ])
        ->and(collect($extras['months'])->pluck('period')->all())->toBe(['2026-06-30']);
});

it('shows the month job by job', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('extras'))
        ->assertOk()
        ->assertSee('June 2026')
        ->assertSee('One')
        ->assertSee('flat, no lines');
});

it('is closed to staff without the financials permission', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('extras'))
        ->assertForbidden();
});

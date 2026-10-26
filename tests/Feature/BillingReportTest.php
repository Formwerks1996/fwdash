<?php

use App\Models\BillingPeriod;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    Project::create(['job_number' => '#1', 'job_name' => 'Alder', 'developer' => 'Acme', 'division' => 'MF', 'active' => true]);
    Project::create(['job_number' => '#2', 'job_name' => 'Birch', 'active' => true]);

    DB::table('project_phase_fees')->insert(['job_number' => '#1', 'phase_code' => 'SD', 'fee' => 40000, 'note' => null]);
    BillingPeriod::create(['period' => '2026-06-30', 'status' => 'open']);
    BillingPeriod::create(['period' => '2026-07-31', 'status' => 'open']);

    DB::table('month_entries')->insert(['period' => '2026-06-30', 'job_number' => '#1', 'phase_code' => 'SD', 'pct_complete' => 0.125, 'amount' => null, 'fee_at_entry' => 40000, 'note' => null]);
    DB::table('hour_lines')->insert(['period' => '2026-06-30', 'job_number' => '#1', 'staff' => 'Adam', 'hours' => 2.5, 'rate' => 160, 'amount' => null, 'rate_source' => 'imported', 'note' => 'site visit']);
    DB::table('month_hourly')->insert(['period' => '2026-06-30', 'job_number' => '#2', 'amount' => 900]);
});

it('prints the month job by job, omitting phases that billed nothing', function () {
    $this->get(route('reports.billing', '2026-06-30'))
        ->assertOk()
        ->assertSee('Monthly Billing Report')
        ->assertSee('$6,300.00')
        ->assertSee('$1,300.00')
        ->assertSee('12.5%')
        ->assertSee('site visit')
        ->assertSee('Hourly billed as a flat figure')
        ->assertDontSee('DD to RZ')
        ->assertSee('Print / Save as PDF');
});

it('downloads as a standalone HTML file', function () {
    $this->get(route('reports.billing', ['period' => '2026-06-30', 'download' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition')
        ->assertDontSee('Print / Save as PDF');
});

it('says so when nothing was billed', function () {
    $this->get(route('reports.billing', '2026-07-31'))->assertOk()->assertSee('Nothing billed in this period.');
});

it('is linked from the Invoicing page', function () {
    $this->get(route('invoicing'))->assertOk()->assertSee(route('reports.billing', '2026-07-31'));
});

it('is closed to staff without the financials permission', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('reports.billing', '2026-06-30'))
        ->assertForbidden();
});

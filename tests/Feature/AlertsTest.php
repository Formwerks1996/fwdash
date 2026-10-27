<?php

use App\Billing\ProjectBurn;
use App\Livewire\Team\Alerts;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectStatus;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** A project with one SD fee of $9,500 (100 fee-hours at $95/h), part billed, and some hours. */
function burningProject(string $job, float $hours, float $billedPct, string $period = '2026-06-30', bool $active = true): void
{
    Project::create(['job_number' => $job, 'job_name' => "Job {$job}", 'developer' => 'Acme', 'active' => $active]);

    DB::table('project_phase_fees')->insert(['job_number' => $job, 'phase_code' => 'SD', 'fee' => 9500]);
    DB::table('job_hours')->insert(['period' => $period, 'job_number' => $job, 'hours' => $hours, 'source' => 'live']);

    if ($billedPct > 0) {
        DB::table('month_entries')->insert(['period' => $period, 'job_number' => $job, 'phase_code' => 'SD', 'pct_complete' => $billedPct, 'fee_at_entry' => 9500]);
    }
}

beforeEach(function () {
    burningProject('#1', hours: 120, billedPct: 0.5);
    burningProject('#2', hours: 85, billedPct: 0.9);
    burningProject('#3', hours: 40, billedPct: 0.4);
    burningProject('#4', hours: 70, billedPct: 0.3);
    burningProject('#5', hours: 300, billedPct: 1, period: '2025-01-31');
});

it('colours projects by one shared rule', function () {
    $burn = app(ProjectBurn::class);

    expect($burn->for('#1')['level'])->toBe('over')
        ->and($burn->for('#2')['level'])->toBe('watch')
        ->and($burn->for('#3')['level'])->toBe('ok')
        ->and($burn->for('#4')['level'])->toBe('watch')
        ->and($burn->for('#1')['feeHours'])->toBe(100.0)
        ->and($burn->for('#1')['currentPhase'])->toBe('SD')
        ->and(ProjectBurn::burnPct(0.996))->toBe('99%');
});

it('lists flagged active projects with hours in the window', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->get(route('alerts'))
        ->assertOk()
        ->assertSee('Project Alerts')
        ->assertSee('Job #1')
        ->assertSee('Job #4')
        ->assertDontSee('Job #3')
        ->assertDontSee('Job #5');
});

it('shows staff the alerts only for projects they are assigned to', function () {
    $staff = User::factory()->staff()->create(['email' => 'pat.tester@formwerks.ca']);
    TeamMember::create(['name' => 'PatTester', 'email' => 'pat.tester@formwerks.ca', 'tier' => 'staff']);
    ProjectAssignment::create(['job_number' => '#4', 'member_name' => 'PatTester', 'role' => 'member', 'set_by' => 'test', 'set_at' => now()]);

    $this->actingAs($staff)
        ->get(route('alerts'))
        ->assertOk()
        ->assertSee('Job #4')
        ->assertDontSee('Job #1')
        ->assertSee('limited to your projects');
});

it('shows staff with no roster entry no alerts', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('alerts'))
        ->assertOk()
        ->assertDontSee('Job #1')
        ->assertDontSee('Job #4');
});

it('shows on-track and paused projects only when asked', function () {
    $this->actingAs(User::factory()->manager()->create());

    ProjectStatus::create(['job_number' => '#2', 'status' => 'paused', 'set_by' => 'test', 'set_at' => now()]);

    Livewire::test(Alerts::class)
        ->assertDontSee('Job #2')
        ->set('paused', true)
        ->assertSee('Job #2')
        ->set('onTrack', true)
        ->assertSee('Job #3')
        ->set('months', 0)
        ->assertSee('Job #5');
});

it('lets a manager change the plan rate, within limits, with an audit entry', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(Alerts::class)
        ->call('editRate')
        ->set('planRate', '10')
        ->call('saveRate')
        ->assertHasErrors(['planRate' => 'plan rate must be between 20 and 500'])
        ->set('planRate', '130')
        ->call('saveRate')
        ->assertHasNoErrors();

    expect(app(ProjectBurn::class)->planRate())->toBe(130.0);

    $this->assertDatabaseHas('audit_logs', ['action' => 'setting.set', 'table_name' => 'app_settings', 'record_key' => 'alerts.plan_rate']);
});

it('shows an empty state before any hours are uploaded', function () {
    DB::table('job_hours')->delete();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('alerts'))
        ->assertOk()
        ->assertSee('No hours uploaded yet.');
});

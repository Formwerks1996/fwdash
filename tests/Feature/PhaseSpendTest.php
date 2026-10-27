<?php

use App\Billing\ProjectBurn;
use App\Livewire\Projects\ProjectsIndex;
use App\Livewire\Projects\ProjectsShow;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectPhaseHours;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function phaseHoursPull(): string
{
    $folder = sys_get_temp_dir().'/mosaic-phases-'.uniqid();
    mkdir($folder);

    $write = fn (string $name, array $payload) => file_put_contents("{$folder}/{$name}.json", json_encode($payload));

    $write('project-100', ['project' => [
        ['mosaic_id' => 500, 'project_number' => '#8113'],
        ['mosaic_id' => 501, 'project_number' => '#9999'],
    ]]);
    $write('time_entry-2026-05', ['time_entries' => [
        ['project_id' => 500, 'phase_name' => 'Feasibility Study', 'hours' => '10', 'rate' => '150.0', 'status' => 'approved'],
        ['project_id' => 500, 'phase_name' => 'Feasibility Study', 'hours' => '4', 'rate' => null, 'status' => 'submitted'],
        ['project_id' => 500, 'phase_name' => 'Design Development to Rezoning', 'hours' => '2', 'rate' => '200.0', 'status' => 'approved'],
        ['project_id' => 500, 'phase_name' => 'Hourly', 'hours' => '3', 'rate' => '100.0', 'status' => 'approved'],
        ['project_id' => 500, 'phase_name' => 'Feasibility Study', 'hours' => '50', 'rate' => '150.0', 'status' => 'rejected'],
        ['project_id' => 501, 'phase_name' => 'Feasibility Study', 'hours' => '8', 'rate' => '150.0', 'status' => 'approved'],
    ]]);

    return $folder;
}

beforeEach(function () {
    Project::create(['job_number' => '#8113', 'job_name' => 'Oak Street', 'active' => true]);

    DB::table('phase_descriptions')->insert(['phase_code' => 'FS', 'description' => 'Feasibility Study']);
    DB::table('project_phase_fees')->insert([
        ['job_number' => '#8113', 'phase_code' => 'FS', 'fee' => 1000],
        ['job_number' => '#8113', 'phase_code' => 'SD', 'fee' => 5000],
    ]);
});

it('totals hours and charge-out per job phase from a Mosaic pull', function () {
    $this->artisan('mosaic:import-phase-hours', ['path' => phaseHoursPull()])->assertSuccessful();

    expect(ProjectPhaseHours::orderBy('mosaic_phase')->get(['mosaic_phase', 'phase_code', 'hours', 'charge_out', 'unrated_hours'])->toArray())->toBe([
        ['mosaic_phase' => 'Design Development to Rezoning', 'phase_code' => 'DD to RZ', 'hours' => '2.00', 'charge_out' => '400.00', 'unrated_hours' => '0.00'],
        ['mosaic_phase' => 'Feasibility Study', 'phase_code' => 'FS', 'hours' => '14.00', 'charge_out' => '1500.00', 'unrated_hours' => '4.00'],
        ['mosaic_phase' => 'Hourly', 'phase_code' => null, 'hours' => '3.00', 'charge_out' => '300.00', 'unrated_hours' => '0.00'],
    ]);
});

it('refuses a folder that is not a Mosaic pull', function () {
    $this->artisan('mosaic:import-phase-hours', ['path' => sys_get_temp_dir().'/nowhere-'.uniqid()])->assertFailed();
});

it('sets each phase’s spend against its fee, pricing unrated hours at the plan rate', function () {
    $this->artisan('mosaic:import-phase-hours', ['path' => phaseHoursPull()]);

    $burn = app(ProjectBurn::class)->for('#8113');
    $phases = collect($burn['phases'])->keyBy('phase');

    expect($phases['FS']['spent'])->toBe(1500.0 + 4 * ProjectBurn::DefaultPlanRate)
        ->and($phases['FS']['spentHours'])->toBe(14.0)
        ->and($phases['SD']['spent'])->toBe(0.0)
        ->and($burn['otherHours'])->toBe(5.0)
        ->and($burn['otherSpent'])->toBe(700.0)
        ->and(collect($burn['otherPhases'])->pluck('name')->all())->toBe(['Hourly', 'Design Development to Rezoning'])
        ->and($burn['spent'])->toBe(1500.0 + 4 * ProjectBurn::DefaultPlanRate);
});

it('shows managers the phase money in dollars and staff in hours', function () {
    $this->artisan('mosaic:import-phase-hours', ['path' => phaseHoursPull()]);

    Livewire::actingAs(User::factory()->manager()->create())
        ->test(ProjectsIndex::class, ['scope' => 'all', 'view' => 'cards'])
        ->assertSee('$2k spent on its fee phases at charge-out, of a $6k fee')
        ->assertSee('−$880')
        ->assertSee('Other phases · no fee, not counted above')
        ->assertSeeInOrder(['Total', '$6k', '$2k', '$4k', 'FS'])
        ->assertSee('Design Development to Rezoning');

    ProjectAssignment::create(['job_number' => '#8113', 'member_name' => 'Nathan', 'role' => 'member', 'set_by' => 'test', 'set_at' => now()]);
    TeamMember::where('name', 'Nathan')->update(['email' => 'nathan.test@formwerks.ca']);

    Livewire::actingAs(User::factory()->staff()->create(['email' => 'nathan.test@formwerks.ca']))
        ->test(ProjectsIndex::class, ['view' => 'cards'])
        ->assertSee('20 h of the 63 h its fee pays for')
        ->assertDontSee('−$880');
});

it('lists the Mosaic phases with no fee on the project page, apart from the contract', function () {
    $this->artisan('mosaic:import-phase-hours', ['path' => phaseHoursPull()]);

    Livewire::actingAs(User::factory()->manager()->create())
        ->test(ProjectsShow::class, ['jobNumber' => '#8113'])
        ->assertSee('Other phases in Mosaic')
        ->assertSee('Design Development to Rezoning')
        ->assertSee('Hourly')
        ->assertSee('$1,880')
        ->assertSee('14.0 h');
});

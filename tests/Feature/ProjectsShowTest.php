<?php

use App\Livewire\Projects\ProjectsShow;
use App\Models\MonthEntry;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Livewire\Livewire;

it('keeps the month note when a percentage is changed on the project page', function () {
    Project::create(['job_number' => '#8114', 'job_name' => 'Loftus 2']);
    MonthEntry::create(['period' => '2026-06-30', 'job_number' => '#8114', 'phase_code' => 'DD', 'pct_complete' => '0.2', 'note' => 'Drawings issued']);

    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(ProjectsShow::class, ['jobNumber' => '#8114'])
        ->call('saveEntry', '2026-06-30', 'DD', 'pct', '40');

    $entry = MonthEntry::sole();

    expect((float) $entry->pct_complete)->toBe(0.4)
        ->and($entry->note)->toBe('Drawings issued');
});

it('shows staff only the projects they are on', function () {
    Project::create(['job_number' => '#8114', 'job_name' => 'Loftus 2', 'active' => true]);
    Project::create(['job_number' => '#8115', 'job_name' => 'Loftus 3', 'active' => true]);
    TeamMember::create(['name' => 'PatTester', 'email' => 'pat.tester@formwerks.ca', 'tier' => 'staff']);
    ProjectAssignment::create(['job_number' => '#8114', 'member_name' => 'PatTester', 'role' => 'member', 'set_by' => 'test', 'set_at' => now()]);

    $this->actingAs(User::factory()->staff()->create(['email' => 'pat.tester@formwerks.ca']));

    $this->get(route('projects.index'))->assertOk()->assertSee('Loftus 2')->assertDontSee('Loftus 3');
    $this->get(route('projects.show', '#8114'))->assertOk();
    $this->get(route('projects.show', '#8115'))->assertForbidden();

    $this->actingAs(User::factory()->manager()->create())
        ->get(route('projects.index'))->assertSee('Loftus 2')->assertSee('Loftus 3');
});

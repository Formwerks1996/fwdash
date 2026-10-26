<?php

use App\Livewire\Projects\ProjectsShow;
use App\Models\MonthEntry;
use App\Models\Project;
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

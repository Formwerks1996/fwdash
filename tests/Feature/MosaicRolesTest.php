<?php

use App\Livewire\Timesheets\Timesheet;
use App\Models\MosaicProjectRole;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function mosaicPull(): string
{
    $folder = sys_get_temp_dir().'/mosaic-'.uniqid();
    mkdir($folder);

    $write = fn (string $name, array $payload) => file_put_contents("{$folder}/{$name}.json", json_encode($payload));

    $write('employee', ['employee' => [
        ['mosaic_id' => 1, 'email' => 'Pat@Formwerks.ca'],
        ['mosaic_id' => 2, 'email' => null],
    ]]);
    $write('role', ['roles' => [['mosaic_id' => 10, 'name' => 'Technologist']]]);
    $write('project-100', ['project' => [
        ['mosaic_id' => 500, 'project_number' => '#8016'],
        ['mosaic_id' => 501, 'project_number' => '#8044'],
        ['mosaic_id' => 502, 'project_number' => null],
    ], 'total_count' => 3]);
    $write('member_project_role', ['member_project_roles' => [
        ['member_id' => 1, 'project_id' => 500, 'role_id' => 10, 'start_date' => '1900-01-01', 'end_date' => null],
        ['member_id' => 1, 'project_id' => 500, 'role_id' => 10, 'start_date' => '1900-01-01', 'end_date' => null],
        ['member_id' => 1, 'project_id' => 501, 'role_id' => 99, 'start_date' => '2026-03-01', 'end_date' => '2026-06-30'],
        ['member_id' => 2, 'project_id' => 500, 'role_id' => 10, 'start_date' => null, 'end_date' => null],
        ['member_id' => 1, 'project_id' => 502, 'role_id' => 10, 'start_date' => null, 'end_date' => null],
    ]]);

    return $folder;
}

it('imports Mosaic project roles keyed by email and job number', function () {
    MosaicProjectRole::create(['email' => 'old@formwerks.ca', 'job_number' => '#1']);

    $this->artisan('mosaic:import-roles', ['path' => mosaicPull()])
        ->expectsOutputToContain('Imported 2 project roles for 1 people on 2 projects.')
        ->expectsOutputToContain('2 roles were skipped')
        ->assertSuccessful();

    expect(MosaicProjectRole::orderBy('job_number')->get(['email', 'job_number', 'role', 'start_date', 'end_date'])->toArray())->toBe([
        ['email' => 'pat@formwerks.ca', 'job_number' => '#8016', 'role' => 'Technologist', 'start_date' => null, 'end_date' => null],
        ['email' => 'pat@formwerks.ca', 'job_number' => '#8044', 'role' => null, 'start_date' => '2026-03-01T00:00:00.000000Z', 'end_date' => '2026-06-30T00:00:00.000000Z'],
    ]);
});

it('refuses a folder that is not a Mosaic pull', function () {
    $this->artisan('mosaic:import-roles', ['path' => sys_get_temp_dir().'/nowhere-'.uniqid()])->assertFailed();
});

it('lists the projects Mosaic has you on first when adding a row', function () {
    CarbonImmutable::setTestNow('2026-10-28 10:00');
    Project::create(['job_number' => '#8016', 'job_name' => 'Seaton', 'active' => true]);
    Project::create(['job_number' => '#8044', 'job_name' => 'Cooper Townhouse', 'active' => true]);
    Project::create(['job_number' => '#7001', 'job_name' => 'Archive Lane', 'active' => false]);

    $user = User::factory()->staff()->create(['email' => 'pat@formwerks.ca']);
    MosaicProjectRole::create(['email' => 'pat@formwerks.ca', 'job_number' => '#8016', 'role' => 'Technologist']);
    MosaicProjectRole::create(['email' => 'pat@formwerks.ca', 'job_number' => '#7001']);
    MosaicProjectRole::create(['email' => 'pat@formwerks.ca', 'job_number' => '#8044', 'end_date' => '2026-06-30']);

    $groups = Livewire::actingAs($user)->test(Timesheet::class)->viewData('projectGroups');

    expect($groups->map->pluck('job_number')->toArray())->toBe([
        'Your projects' => ['#8016', '#7001'],
        'Other active projects' => ['#8044'],
    ]);
});

<?php

use App\Livewire\Projects\ProjectRoles;
use App\Livewire\Projects\ProjectsIndex;
use App\Livewire\Team\PmDesk;
use App\Models\Project;
use App\Models\ProjectRole;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('starts Andrew and Nikolas as leads of their projects and Nathan as PM of 4855 Paton St', function () {
    Schema::drop('project_roles');

    Project::create(['job_number' => '#1026', 'job_name' => '4855 Paton St', 'project_lead' => 'Andrew', 'active' => true]);
    Project::create(['job_number' => '#8016', 'job_name' => 'Seaton', 'project_lead' => 'Nikolas', 'active' => true]);
    Project::create(['job_number' => '#8037', 'job_name' => 'West 26th and Ash', 'project_lead' => 'Norman', 'active' => true]);

    (include database_path('migrations/2026_10_28_100000_create_project_roles_table.php'))->up();

    expect(ProjectRole::orderBy('job_number')->orderBy('role')->get(['job_number', 'member_name', 'role'])->toArray())->toBe([
        ['job_number' => '#1026', 'member_name' => 'Andrew', 'role' => ProjectRole::Lead],
        ['job_number' => '#1026', 'member_name' => 'Nathan', 'role' => ProjectRole::ProjectManager],
        ['job_number' => '#8016', 'member_name' => 'Nikolas', 'role' => ProjectRole::Lead],
    ]);
});

it('puts the projects someone leads or manages on their PM Desk with who leads and who manages each', function () {
    Project::create(['job_number' => '#1026', 'job_name' => '4855 Paton St', 'active' => true]);
    Project::create(['job_number' => '#8016', 'job_name' => 'Seaton', 'active' => true]);
    ProjectRole::create(['job_number' => '#1026', 'member_name' => 'Andrew', 'role' => ProjectRole::Lead, 'set_by' => 'test', 'set_at' => now()]);
    ProjectRole::create(['job_number' => '#1026', 'member_name' => 'Nathan', 'role' => ProjectRole::ProjectManager, 'set_by' => 'test', 'set_at' => now()]);
    ProjectRole::create(['job_number' => '#8016', 'member_name' => 'Nikolas', 'role' => ProjectRole::Lead, 'set_by' => 'test', 'set_at' => now()]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(PmDesk::class, ['member' => 'Nathan'])
        ->assertSee('4855 Paton St')
        ->assertSee('You’re the PM')
        ->assertDontSee('Seaton');

    Livewire::test(PmDesk::class, ['member' => 'Andrew'])
        ->assertSee('4855 Paton St')
        ->assertSee('You lead this')
        ->assertDontSee('Seaton');
});

it('lists each project’s lead and PM on the Projects pages', function () {
    Project::create(['job_number' => '#1026', 'job_name' => '4855 Paton St', 'active' => true]);
    Project::create(['job_number' => '#8016', 'job_name' => 'Seaton', 'active' => true]);
    ProjectRole::create(['job_number' => '#1026', 'member_name' => 'Andrew', 'role' => ProjectRole::Lead, 'set_by' => 'test', 'set_at' => now()]);
    ProjectRole::create(['job_number' => '#1026', 'member_name' => 'Nathan', 'role' => ProjectRole::ProjectManager, 'set_by' => 'test', 'set_at' => now()]);

    $rows = Livewire::actingAs(User::factory()->manager()->create())
        ->test(ProjectsIndex::class, ['group' => 'all', 'scope' => 'all'])
        ->set('search', 'nathan')
        ->instance()->rows;

    expect($rows->map(fn (array $row) => Arr::only($row, ['jobNumber', 'leads', 'pms']))->all())->toBe([
        ['jobNumber' => '#1026', 'leads' => 'Andrew', 'pms' => 'Nathan'],
    ]);

    $this->get(route('projects.show', '#1026'))->assertOk()->assertSee('Lead')->assertSee('Andrew')->assertSee('PM');
});

it('lets managers set and clear a project’s lead and PM on Leads & PMs', function () {
    Project::create(['job_number' => '#1026', 'job_name' => '4855 Paton St', 'active' => true]);
    ProjectRole::create(['job_number' => '#1026', 'member_name' => 'Nathan', 'role' => ProjectRole::ProjectManager, 'set_by' => 'test', 'set_at' => now()]);

    $this->actingAs(User::factory()->manager()->create());
    $this->get(route('project-roles'))->assertOk()->assertSee('4855 Paton St');

    Livewire::test(ProjectRoles::class)
        ->call('setRole', '#1026', ProjectRole::Lead, 'Andrew')
        ->call('setRole', '#1026', ProjectRole::ProjectManager, 'Julie')
        ->assertHasNoErrors();

    expect(ProjectRole::byJob()['#1026'])->toBe([ProjectRole::Lead => ['Andrew'], ProjectRole::ProjectManager => ['Julie']]);
    $this->assertDatabaseHas('audit_logs', ['table_name' => 'project_roles', 'record_key' => '#1026|pm', 'action' => 'update']);

    Livewire::test(ProjectRoles::class)->call('setRole', '#1026', ProjectRole::ProjectManager, '');

    expect(ProjectRole::byJob()['#1026'])->toBe([ProjectRole::Lead => ['Andrew'], ProjectRole::ProjectManager => []]);
});

it('refuses an unknown person or role on Leads & PMs', function () {
    Project::create(['job_number' => '#1026', 'job_name' => '4855 Paton St', 'active' => true]);

    Livewire::actingAs(User::factory()->manager()->create())
        ->test(ProjectRoles::class)
        ->call('setRole', '#1026', ProjectRole::ProjectManager, 'Nobody')
        ->assertHasErrors(['role' => 'unknown team member Nobody'])
        ->call('setRole', '#1026', 'boss', 'Andrew')
        ->assertHasErrors(['role']);

    expect(ProjectRole::count())->toBe(0);
});

it('keeps Leads & PMs to managers', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('project-roles'))
        ->assertForbidden();

    Project::create(['job_number' => '#1026', 'job_name' => '4855 Paton St', 'active' => true]);

    Livewire::actingAs(User::factory()->staff()->create())
        ->test(ProjectRoles::class)
        ->call('setRole', '#1026', ProjectRole::ProjectManager, 'Nathan')
        ->assertForbidden();
});

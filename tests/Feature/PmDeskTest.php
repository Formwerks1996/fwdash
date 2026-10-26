<?php

use App\Livewire\Projects\ProjectsShow;
use App\Livewire\Team\PmDesk;
use App\Models\LineupWeek;
use App\Models\PmTask;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Project::create(['job_number' => '#8113', 'job_name' => 'Oak Street', 'developer' => 'Acme', 'active' => true]);
    Project::create(['job_number' => '#8120', 'job_name' => 'Elm Court', 'active' => true]);

    DB::table('project_phase_fees')->insert(['job_number' => '#8113', 'phase_code' => 'SD', 'fee' => 9500]);
    DB::table('job_hours')->insert(['period' => '2026-06-30', 'job_number' => '#8113', 'hours' => 40, 'source' => 'live']);
});

it('opens the signed-in person’s own desk', function () {
    ProjectAssignment::create(['job_number' => '#8113', 'member_name' => 'Julie', 'role' => 'pm', 'set_by' => 'test', 'set_at' => now()]);

    $this->actingAs(User::factory()->manager()->create(['email' => 'julie@formwerks.ca']))
        ->get(route('pm-desk'))
        ->assertOk()
        ->assertSee('Julie VanGrootheest')
        ->assertSee('Oak Street')
        ->assertSee('now in');
});

it('opens another person’s desk by name and 404s for strangers', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('pm-desk', 'Dasha'))->assertOk()->assertSee('Dasha Izmaylov');
    $this->get(route('pm-desk', 'Nobody'))->assertNotFound();
});

it('keeps the desk away from staff', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('pm-desk'))
        ->assertForbidden();
});

it('includes projects the person or their team is tagged to this week', function () {
    $this->actingAs(User::factory()->manager()->create());

    $monday = CarbonImmutable::now(config('app.display_timezone'))->startOfWeek()->toDateString();
    LineupWeek::updateOrCreate(['week' => $monday], ['placements' => ['Nathan' => 'Julie'], 'tags' => ['Nathan' => '#8120'], 'badges' => []]);

    Livewire::test(PmDesk::class, ['member' => 'Julie'])
        ->assertSee('Elm Court')
        ->assertSee('Tagged this week: Nathan')
        ->call('claimTagged');

    $this->assertDatabaseHas('project_assignments', ['job_number' => '#8120', 'member_name' => 'Julie']);
});

it('claims and releases a project with audit entries', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(PmDesk::class, ['member' => 'Josh'])
        ->set('projectSearch', 'oak')
        ->assertSee('Oak Street')
        ->call('claim', '#8113')
        ->call('claim', '#8113')
        ->call('claim', '#0000')
        ->assertHasErrors(['assignment' => 'unknown project #0000'])
        ->call('release', '#8113');

    expect(ProjectAssignment::count())->toBe(0);

    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseHas('audit_logs', ['action' => 'insert', 'table_name' => 'project_assignments', 'record_key' => '#8113|Josh']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'delete', 'table_name' => 'project_assignments', 'record_key' => '#8113|Josh']);
});

it('adds, ticks and deletes tasks', function () {
    $this->actingAs(User::factory()->manager()->create());

    ProjectAssignment::create(['job_number' => '#8113', 'member_name' => 'Josh', 'role' => 'pm', 'set_by' => 'test', 'set_at' => now()]);

    $component = Livewire::test(PmDesk::class, ['member' => 'Josh'])
        ->set('newTask.job8113.title', '')
        ->call('addTask', '#8113')
        ->assertHasErrors('task')
        ->set('newTask.job8113.title', 'Send DP drawings')
        ->set('newTask.job8113.assignee', 'Josh')
        ->call('addTask', '#8113')
        ->assertSee('Send DP drawings');

    $task = PmTask::sole();

    $component->call('toggleTask', $task->id);
    expect($task->refresh()->done_at)->not->toBeNull();

    $component->call('deleteTask', $task->id);
    expect(PmTask::count())->toBe(0);

    foreach (['task.add', 'task.set', 'task.delete'] as $action) {
        $this->assertDatabaseHas('audit_logs', ['action' => $action, 'table_name' => 'pm_tasks', 'record_key' => (string) $task->id]);
    }
});

it('needs edit team to change a desk', function () {
    $user = User::factory()->staff()->create();
    $user->givePermissionTo('view financials');

    $this->actingAs($user);

    Livewire::test(PmDesk::class, ['member' => 'Josh'])
        ->call('claim', '#8113')
        ->assertForbidden();
});

it('assigns and releases people from the project page', function () {
    $this->actingAs(User::factory()->manager()->create());

    Project::create(['job_number' => '#77', 'job_name' => 'Seventy Seven']);
    $member = TeamMember::active()->firstOrFail()->name;

    Livewire::test(ProjectsShow::class, ['jobNumber' => '#77'])
        ->call('assign', $member)
        ->assertSee($member);

    $this->assertDatabaseHas('project_assignments', ['job_number' => '#77', 'member_name' => $member]);

    Livewire::test(ProjectsShow::class, ['jobNumber' => '#77'])->call('release', $member);

    $this->assertDatabaseMissing('project_assignments', ['job_number' => '#77', 'member_name' => $member]);
});

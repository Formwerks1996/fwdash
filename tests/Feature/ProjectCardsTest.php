<?php

use App\Livewire\Projects\ProjectsShow;
use App\Livewire\Projects\ProjectsIndex;
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

it('shows staff their own projects as cards', function () {
    ProjectAssignment::create(['job_number' => '#8113', 'member_name' => 'Nathan', 'role' => 'member', 'set_by' => 'test', 'set_at' => now()]);
    TeamMember::where('name', 'Nathan')->update(['email' => 'nathan.test@formwerks.ca']);

    $this->actingAs(User::factory()->staff()->create(['email' => 'nathan.test@formwerks.ca']))
        ->get(route('projects.index', ['view' => 'cards', 'group' => 'all']))
        ->assertOk()
        ->assertSee('Oak Street')
        ->assertSee('now in')
        ->assertSee('fee-hours used')
        ->assertDontSee('Elm Court')
        ->assertDontSee('Rate performance')
        ->assertSee(route('projects.show', '#8113'));
});

it('lets managers pick one person’s projects, but not staff', function () {
    ProjectAssignment::create(['job_number' => '#8113', 'member_name' => 'Julie', 'role' => 'pm', 'set_by' => 'test', 'set_at' => now()]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(ProjectsIndex::class, ['group' => 'all'])
        ->assertSee('Elm Court')
        ->set('person', 'Julie')
        ->assertSee('Oak Street')
        ->assertDontSee('Elm Court')
        ->set('person', 'Nobody')
        ->assertSee('Elm Court');

    Livewire::actingAs(User::factory()->staff()->create())
        ->test(ProjectsIndex::class, ['group' => 'all', 'person' => 'Julie'])
        ->assertDontSee('Oak Street');
});

it('includes the project a person is tagged to this week', function () {
    $monday = CarbonImmutable::now(config('app.display_timezone'))->startOfWeek()->toDateString();
    LineupWeek::updateOrCreate(['week' => $monday], ['placements' => ['Nathan' => 'Julie'], 'tags' => ['Nathan' => '#8120'], 'badges' => []]);

    Livewire::actingAs(User::factory()->manager()->create())
        ->test(ProjectsIndex::class, ['group' => 'all', 'person' => 'Nathan', 'view' => 'cards'])
        ->assertSee('Elm Court')
        ->assertDontSee('Oak Street');
});

it('sends old PM Desk links to the card view', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('pm-desk'))->assertRedirect(route('projects.index', ['view' => 'cards']));
    $this->get(route('pm-desk', 'Dasha'))->assertRedirect(route('projects.index', ['view' => 'cards', 'person' => 'Dasha']));
});

it('adds, ticks and deletes tasks on a card', function () {
    $component = Livewire::actingAs(User::factory()->manager()->create())
        ->test(ProjectsIndex::class, ['group' => 'all', 'scope' => 'all', 'view' => 'cards'])
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

it('needs edit team to add a task', function () {
    $user = User::factory()->staff()->create();
    $user->givePermissionTo('view financials');

    Livewire::actingAs($user)
        ->test(ProjectsIndex::class)
        ->set('newTask.job8113.title', 'Nope')
        ->call('addTask', '#8113')
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

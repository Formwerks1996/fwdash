<?php

use App\Livewire\Team\MyWeek;
use App\Livewire\Team\TeamLineup;
use App\Models\LineupWeek;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    Project::create(['job_number' => '#8113', 'job_name' => 'Oak Street', 'developer' => 'Acme', 'active' => true]);
    Project::create(['job_number' => '#8120', 'job_name' => 'Elm Court', 'active' => true]);
    Project::create(['job_number' => '#8130', 'job_name' => 'Pine Row', 'active' => true]);

    TeamMember::where('name', 'Nathan')->update(['email' => 'nathan.test@formwerks.ca']);
});

it('shows this week\'s lineup project, then every assigned project as ongoing, each with its PM', function () {
    LineupWeek::create(['week' => TeamLineup::thisMonday(), 'placements' => ['Nathan' => 'Julie'], 'tags' => ['Nathan' => '#8120'], 'badges' => []]);
    ProjectAssignment::create(['job_number' => '#8113', 'member_name' => 'Nathan', 'role' => 'member', 'set_by' => 'test', 'set_at' => now()]);
    ProjectAssignment::create(['job_number' => '#8113', 'member_name' => 'Adam', 'role' => 'pm', 'set_by' => 'test', 'set_at' => now()]);

    $this->actingAs(User::factory()->staff()->create(['email' => 'nathan.test@formwerks.ca']))
        ->get(route('my-week'))
        ->assertOk()
        ->assertSeeInOrder(['Julie VanGrootheest', 'This week', 'Elm Court', 'Julie VanGrootheest', 'Ongoing', 'Oak Street', 'Adam Chan'])
        ->assertDontSee('Pine Row');
});

it('moves between weeks', function () {
    $nextMonday = CarbonImmutable::parse(TeamLineup::thisMonday())->addWeek()->toDateString();
    LineupWeek::create(['week' => $nextMonday, 'placements' => [], 'tags' => ['Nathan' => '#8130'], 'badges' => []]);

    $this->actingAs(User::factory()->staff()->create(['email' => 'nathan.test@formwerks.ca']));

    Livewire::test(MyWeek::class)
        ->assertDontSee('Pine Row')
        ->call('nextWeek')
        ->assertSet('week', $nextMonday)
        ->assertSee('Pine Row');
});

it('says so when the sign-in is not on the roster', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('my-week'))
        ->assertOk()
        ->assertSee('not linked to anyone on the team roster');
});

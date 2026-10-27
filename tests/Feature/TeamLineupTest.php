<?php

use App\Livewire\Team\TeamLineup;
use App\Models\LineupWeek;
use App\Models\Project;
use App\Models\User;
use App\Support\LineupTree;
use Livewire\Livewire;

beforeEach(function () {
    Project::create(['job_number' => '#8113', 'job_name' => 'Oak Street', 'active' => true]);
});

it('keeps the lineup from staff and shows it with editing to admins', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('lineup', ['week' => '2026-10-19']))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('lineup'))
        ->assertOk()
        ->assertSee('Copy last week')
        ->assertSee('Roster');
});

it('refuses a week that is not a Monday', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('lineup', ['week' => '2026-10-21']))
        ->assertStatus(400);
});

it('does not let read-only users change the lineup', function () {
    $this->actingAs(User::factory()->staff()->create());

    Livewire::test(TeamLineup::class, ['week' => '2026-10-26'])
        ->call('move', 'Nathan', 'under:Josh')
        ->assertForbidden();

    expect(LineupWeek::find('2026-10-26'))->toBeNull();
});

it('saves a week the first time a name moves, with an audit entry', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(TeamLineup::class, ['week' => '2026-10-26'])
        ->call('move', 'Nathan', 'under:Julie')
        ->assertSet('status', 'Saved')
        ->assertDispatched('lineup-toast', message: 'Nathan Lam now goes to Julie VanGrootheest', undo: true);

    expect(LineupWeek::find('2026-10-26')->placements)->toBe(['Nathan' => 'Julie']);

    $this->assertDatabaseHas('audit_logs', ['action' => 'lineup.set', 'table_name' => 'lineup_weeks', 'record_key' => '2026-10-26', 'before' => null]);
});

it('moves a whole branch and takes a PM off with "none"', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(TeamLineup::class, ['week' => '2026-10-26'])
        ->call('move', 'Nathan', 'under:Julie')
        ->call('move', 'Julie', 'tray')
        ->assertDispatched('lineup-toast', message: 'Julie VanGrootheest moved to Not placed (and 1 person under Julie)');

    expect(LineupWeek::find('2026-10-26')->placements)->toBe(['Julie' => 'none', 'Nathan' => 'Julie']);
});

it('refuses a loop', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(TeamLineup::class, ['week' => '2026-10-26'])
        ->call('move', 'Nathan', 'under:Julie')
        ->call('move', 'Julie', 'under:Nathan')
        ->assertSet('statusIsError', true)
        ->assertSet('status', "Couldn't save: that would put Julie VanGrootheest under their own team");

    expect(LineupWeek::find('2026-10-26')->placements)->toBe(['Nathan' => 'Julie']);
});

it('tags people with projects from the list only, and badges them', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(TeamLineup::class, ['week' => '2026-10-26'])
        ->call('setTag', 'Nathan', '#8113')
        ->call('setTag', 'Kendra', '#9999')
        ->assertSet('status', "Couldn't save: project tags must be projects from the Projects list (not found: #9999)")
        ->call('setBadge', 'Nathan', 'help', 'DP resubmission Thu')
        ->assertSee('DP resubmission Thu')
        ->call('setBadge', 'Kendra', 'deadline', null)
        ->assertSet('status', "Couldn't save: a badge is either help or available");

    $week = LineupWeek::find('2026-10-26');

    expect($week->tags)->toBe(['Nathan' => '#8113'])
        ->and($week->badges)->toBe(['Nathan' => ['type' => 'help', 'note' => 'DP resubmission Thu']]);
});

it('reads deadline badges saved before it was dropped as needing help', function () {
    $week = LineupWeek::create(['week' => '2026-10-26', 'placements' => [], 'tags' => [], 'badges' => ['Nathan' => ['type' => 'deadline', 'note' => 'DP Thu']]]);

    expect(LineupTree::forWeek($week)->keepBadges($week->badges))
        ->toBe(['Nathan' => ['type' => 'help', 'note' => 'DP Thu']]);
});

it('undoes the last change', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(TeamLineup::class, ['week' => '2026-10-26'])
        ->call('move', 'Nathan', 'under:Julie')
        ->call('setTag', 'Nathan', '#8113')
        ->call('undoLast')
        ->assertDispatched('lineup-toast', message: 'Undone', undo: true);

    expect(LineupWeek::find('2026-10-26')->tags)->toBe([])
        ->and(LineupWeek::find('2026-10-26')->placements)->toBe(['Nathan' => 'Julie']);
});

it('copies last week without its badges and erases back to the defaults', function () {
    $this->actingAs(User::factory()->manager()->create());

    $component = Livewire::test(TeamLineup::class, ['week' => '2026-10-26'])
        ->call('setBadge', 'Kendra', 'help', null)
        ->call('copyLastWeek')
        ->assertDispatched('lineup-toast', message: "Copied last week's lineup");

    $week = LineupWeek::find('2026-10-26');

    expect($week->placements)->toBe(['Alishah' => 'Dasha', 'Josh' => 'none', 'Julie' => 'Nikolas'])
        ->and($week->badges)->toBe(['Kendra' => ['type' => 'help']]);

    $component->call('erase');

    expect(LineupWeek::find('2026-10-26')->only(['placements', 'tags', 'badges']))->toBe(['placements' => [], 'tags' => [], 'badges' => []]);
});

it('says so when there is no week before to copy', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(TeamLineup::class, ['week' => '2027-03-01'])
        ->call('copyLastWeek')
        ->assertDispatched('lineup-toast', message: 'Nothing was set up for the week before.');

    expect(LineupWeek::find('2027-03-01'))->toBeNull();
});

it('suggests a person’s own projects first in the tag picker', function () {
    $this->actingAs(User::factory()->manager()->create());

    LineupWeek::find('2026-10-19')->update(['tags' => ['Nathan' => '#8113']]);

    $choices = Livewire::test(TeamLineup::class, ['week' => '2026-10-26'])->instance()->projectChoices('Nathan');

    expect($choices['mine'])->toBe([['number' => '#8113', 'note' => 'tagged last week']])
        ->and($choices['all'])->toHaveCount(1);
});

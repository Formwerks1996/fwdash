<?php

use App\Actions\Team\SaveTeamMemberAction;
use App\Imports\DashboardWorkbookImporter;
use App\Models\LineupWeek;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\LineupTree;
use Database\Seeders\TeamSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('seeds the roster and the three real lineup weeks once', function () {
    (new TeamSeeder)->run();

    expect(TeamMember::count())->toBe(19)
        ->and(TeamMember::where('tier', 'partner')->orderBy('name')->pluck('name')->all())->toBe(['Andrew', 'Nikolas'])
        ->and(LineupWeek::orderBy('week')->pluck('week')->all())->toBe(['2026-10-05', '2026-10-12', '2026-10-19'])
        ->and(LineupWeek::find('2026-10-19')->placements)->toBe(['Alishah' => 'Dasha', 'Dasha' => 'Nikolas', 'Josh' => 'none', 'Julie' => 'Nikolas', 'Vrinda' => 'Andrew']);
});

it('does not undo roster edits when the seeder runs again', function () {
    TeamMember::where('name', 'Josh')->update(['active' => false]);

    (new TeamSeeder)->run();

    expect(TeamMember::where('name', 'Josh')->value('active'))->toBeFalsy();
});

it('starts unplaced PMs at their default partner, keeps "none" and cuts loops', function () {
    $tree = LineupTree::fromPlacements(LineupTree::roster(), [
        'Josh' => 'none',
        'Edward' => 'Kendra',
        'Kendra' => 'Edward',
        'Nathan' => 'Julie',
        'Ghost' => 'Andrew',
    ]);

    expect($tree->parentOf('Julie'))->toBe('Andrew')
        ->and($tree->parentOf('Dasha'))->toBe('Nikolas')
        ->and($tree->boss['Josh'])->toBe('none')
        ->and($tree->parentOf('Josh'))->toBeNull()
        ->and($tree->parentOf('Nathan'))->toBe('Julie')
        ->and($tree->isPlaced('Nathan'))->toBeTrue()
        ->and([$tree->parentOf('Edward'), $tree->parentOf('Kendra')])->toContain(null)
        ->and($tree->has('Ghost'))->toBeFalse();
});

it('refuses moves that would loop or move a partner', function () {
    $tree = LineupTree::fromPlacements(LineupTree::roster(), ['Nathan' => 'Julie']);

    expect($tree->refusal('Julie', 'under:Nathan'))->toBe('that would put Julie VanGrootheest under their own team')
        ->and($tree->refusal('Andrew', 'under:Julie'))->toBe('partners cannot be moved')
        ->and($tree->refusal('Kendra', 'tray'))->not->toBeNull()
        ->and($tree->accepts('Julie', 'under:Dasha'))->toBeTrue();
});

it('stores only the links that differ from a PM default', function () {
    $tree = LineupTree::fromPlacements(LineupTree::roster(), [])->moved('Nathan', 'under:Julie')->moved('Josh', 'tray');

    expect($tree->toPlacements())->toBe(['Josh' => 'none', 'Nathan' => 'Julie']);
});

it('summarises a week per partner', function () {
    $summary = LineupTree::forWeek(LineupWeek::find('2026-10-19'))->summary();

    expect($summary['sides'][0])->toMatchArray(['first' => 'Andrew', 'teams' => 1, 'people' => 1])
        ->and($summary['sides'][1])->toMatchArray(['first' => 'Nikolas', 'teams' => 2, 'people' => 3]);
});

it('adds, edits and deletes roster people with an audit trail', function () {
    $save = app(SaveTeamMemberAction::class);

    $member = $save->execute(['name' => 'Priya', 'email' => 'Priya@Formwerks.ca', 'tier' => 'staff']);

    expect($member->email)->toBe('priya@formwerks.ca')
        ->and($member->grp)->toBe('other');

    $save->execute(['tier' => 'pm', 'default_boss' => 'Andrew'], $member);
    $save->delete($member->refresh());

    expect(TeamMember::where('name', 'Priya')->exists())->toBeFalse();

    foreach (['team.add', 'team.set', 'team.delete'] as $action) {
        $this->assertDatabaseHas('audit_logs', ['action' => $action, 'table_name' => 'team_members', 'record_key' => 'Priya']);
    }
});

it('keeps the lineup to two partners', function () {
    app(SaveTeamMemberAction::class)->execute(['name' => 'Priya', 'tier' => 'partner']);
})->throws(ValidationException::class, 'The lineup has room for two partners.');

it('only lets a partner be a default boss', function () {
    app(SaveTeamMemberAction::class)->execute(['name' => 'Priya', 'tier' => 'pm', 'default_boss' => 'Josh']);
})->throws(ValidationException::class, 'A default boss must be one of the partners.');

it('keeps the team tables out of what a workbook upload replaces', function () {
    expect(array_intersect(DashboardWorkbookImporter::ReplacedTables, ['team_members', 'project_assignments', 'pm_tasks', 'lineup_weeks']))->toBe([]);
});

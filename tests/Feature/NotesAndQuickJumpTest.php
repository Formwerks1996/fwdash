<?php

use App\Livewire\StickyNotes;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\StickyNote;
use App\Models\TeamMember;
use App\Models\User;
use Livewire\Livewire;

it('creates, edits, moves, deletes and restores notes', function () {
    $this->actingAs(User::factory()->staff()->create(['name' => 'Pat']));

    $component = Livewire::test(StickyNotes::class, ['label' => 'Software'])
        ->call('create', '  Renew Adobe  ', 1.4, -5);

    $note = StickyNote::sole();

    expect($note->body)->toBe('Renew Adobe')
        ->and((float) $note->pos_x)->toBe(1.0)
        ->and($note->pos_y)->toBe(0)
        ->and($note->author)->toBe('Pat');

    $component->call('edit', $note->id, 'Renew Adobe in March')->call('move', $note->id, 0.25, 300);

    expect($note->fresh()->body)->toBe('Renew Adobe in March')
        ->and($note->fresh()->pos_y)->toBe(300);

    $component->call('delete', $note->id);
    expect(StickyNote::count())->toBe(0);

    $component->call('restore');
    expect(StickyNote::count())->toBe(1);

    $component->call('create', '   ', 0.5, 10)->assertHasErrors('note');
});

it('pins notes to the record in the address, not to route defaults', function () {
    Project::create(['job_number' => '#8114', 'job_name' => 'Loftus 2']);

    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('help'))->assertOk();
    $this->get(route('projects.show', '#8114'))->assertOk();
});

it('jumps only to what the user may open', function () {
    Project::create(['job_number' => '#8114', 'job_name' => 'Loftus 2', 'developer' => 'Polygon']);
    Project::create(['job_number' => '#8115', 'job_name' => 'Loftus 3', 'developer' => 'Polygon']);
    TeamMember::create(['name' => 'PatTester', 'email' => 'pat.tester@formwerks.ca', 'tier' => 'staff']);
    ProjectAssignment::create(['job_number' => '#8114', 'member_name' => 'PatTester', 'role' => 'member', 'set_by' => 'test', 'set_at' => now()]);

    $this->actingAs(User::factory()->staff()->create(['email' => 'pat.tester@formwerks.ca']))
        ->getJson(route('quickJump'))
        ->assertOk()
        ->assertJsonFragment(['label' => '#8114 Loftus 2'])
        ->assertJsonMissing(['label' => '#8115 Loftus 3'])
        ->assertJsonMissing(['label' => 'Software'])
        ->assertJsonMissing(['kind' => 'Developer']);

    $this->actingAs(User::factory()->manager()->create())
        ->getJson(route('quickJump'))
        ->assertJsonFragment(['label' => '#8114 Loftus 2'])
        ->assertJsonFragment(['kind' => 'Developer', 'label' => 'Polygon']);
});

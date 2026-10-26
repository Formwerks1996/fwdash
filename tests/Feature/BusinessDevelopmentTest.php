<?php

use App\Actions\BusinessDevelopment\MergeDevelopersAction;
use App\Billing\DeveloperIndex;
use App\Enums\PursuitStage;
use App\Livewire\BusinessDevelopment\DevelopersIndex;
use App\Livewire\BusinessDevelopment\DevelopersShow;
use App\Livewire\BusinessDevelopment\NewPursuit;
use App\Livewire\BusinessDevelopment\Pipeline;
use App\Models\Developer;
use App\Models\Project;
use App\Models\Pursuit;
use App\Models\User;
use App\Support\DeveloperKey;
use Livewire\Livewire;

beforeEach(function () {
    Project::create(['job_number' => '#1', 'job_name' => 'One', 'developer' => 'Polygon Homes', 'active' => true]);
    Project::create(['job_number' => '#2', 'job_name' => 'Two', 'developer' => 'POLYGON HOMES', 'active' => false]);
    Project::create(['job_number' => '#3', 'job_name' => 'Three', 'developer' => 'Polygon Homes Ltd.', 'active' => true]);
});

it('folds developer spellings onto one key', function () {
    expect(DeveloperKey::make('Polygon Homes'))->toBe('polygonhomes')
        ->and(DeveloperKey::make('Développement Été'))->toBe('développementété')
        ->and(DeveloperKey::make('...'))->toBe('');

    $rows = app(DeveloperIndex::class)->all();

    expect($rows)->toHaveKeys(['polygonhomes', 'polygonhomesltd'])
        ->and($rows['polygonhomes']['jobCount'])->toBe(2)
        ->and($rows['polygonhomes']['liveCount'])->toBe(1);
});

it('reports possible duplicates and merges them without retyping projects', function () {
    expect(app(DeveloperIndex::class)->possibleDuplicates())->toHaveCount(1);

    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(DevelopersIndex::class)->call('merge', 'polygonhomes', 'polygonhomesltd');

    $index = app(DeveloperIndex::class);
    $index->forget();

    expect($index->all())->not->toHaveKey('polygonhomesltd')
        ->and($index->find('polygonhomesltd')['key'])->toBe('polygonhomes')
        ->and($index->find('polygonhomes')['jobCount'])->toBe(3)
        ->and(Project::find('#3')->developer)->toBe('Polygon Homes Ltd.');

    $this->assertDatabaseHas('audit_logs', ['action' => 'merge', 'table_name' => 'developer_aliases', 'record_key' => 'polygonhomesltd']);
});

it('never forms a chain of merges', function () {
    $merge = app(MergeDevelopersAction::class);

    $merge->merge('B', 'C');
    $merge->merge('A', 'B');

    $this->assertDatabaseHas('developer_aliases', ['alias_key' => 'a', 'canon_key' => 'c']);

    $merge->merge('C', 'D');

    $this->assertDatabaseHas('developer_aliases', ['alias_key' => 'a', 'canon_key' => 'd']);
    $this->assertDatabaseHas('developer_aliases', ['alias_key' => 'b', 'canon_key' => 'd']);
});

it('opens a merged-away developer at the firm it now belongs to and can separate it', function () {
    $this->actingAs(User::factory()->manager()->create());

    app(MergeDevelopersAction::class)->merge('Polygon Homes Ltd.', 'Polygon Homes', 'Polygon Homes Ltd.');

    Livewire::test(DevelopersShow::class, ['developerKey' => 'polygonhomesltd'])
        ->assertSet('developerKey', 'polygonhomes')
        ->assertSee('Polygon Homes Ltd.')
        ->call('separate', 'polygonhomesltd');

    $this->assertDatabaseMissing('developer_aliases', ['alias_key' => 'polygonhomesltd']);
});

it('saves a city onto a billing-only developer', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(DevelopersShow::class, ['developerKey' => 'polygonhomes'])->call('saveField', 'city', 'Vancouver');

    expect(Developer::firstWhere('key', 'polygonhomes')->city)->toBe('Vancouver');
});

it('edits pursuits cell by cell and orders settled ones last', function () {
    $this->actingAs(User::factory()->manager()->create());

    $component = Livewire::test(Pipeline::class)->call('add')->call('add');

    [$first, $second] = Pursuit::orderBy('id')->pluck('id')->all();

    $component
        ->call('updateField', $first, 'stage', 'Won')
        ->call('updateField', $second, 'fee', '$75,000')
        ->call('updateField', $second, 'owner', 'Nik')
        ->assertSet('order', [$second, $first]);

    expect(Pursuit::find($second)->fee)->toBe('75000.00')
        ->and(Pursuit::find($first)->stage)->toBe(PursuitStage::Won);

    $component->call('updateField', $second, 'owner', 'Nathan')->assertHasErrors('owner');
    $component->call('updateField', $second, 'fee', 'abc')->assertHasErrors('fee');
});

it('creates a developer profile from the pipeline picker', function () {
    $this->actingAs(User::factory()->manager()->create());

    $component = Livewire::test(Pipeline::class)->call('add');

    $component->call('createDeveloper', Pursuit::sole()->id, 'Brand New Dev')->assertSet('addedDeveloper', 'Brand New Dev');

    expect(Pursuit::sole()->developer)->toBe('Brand New Dev')
        ->and(Developer::firstWhere('key', 'brandnewdev'))->not->toBeNull();
});

it('adds a pursuit from the header form', function () {
    $this->actingAs(User::factory()->manager()->create());

    Livewire::test(NewPursuit::class)
        ->call('show')
        ->call('save')
        ->assertHasErrors('project');

    Livewire::test(NewPursuit::class)
        ->call('show')
        ->set('client', 'polygon homes')
        ->set('project', '123 Main')
        ->set('fee', '50000')
        ->call('save')
        ->assertRedirect(route('pipeline'));

    expect(Pursuit::sole()->developer)->toBe('Polygon Homes');
});

it('keeps business development from staff', function () {
    $this->actingAs(User::factory()->staff()->create());

    foreach (['pipeline', 'developers.index', 'outreach.index'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

it('renders every business development page for managers', function () {
    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('pipeline'))->assertOk();
    $this->get(route('developers.index'))->assertOk()->assertSee('Polygon Homes');
    $this->get(route('developers.show', 'polygonhomes'))->assertOk();
    $this->get(route('outreach.index'))->assertOk()->assertSee('No outreach research loaded yet');
    $this->get(route('help'))->assertOk()->assertSee('Version 2.0');
});

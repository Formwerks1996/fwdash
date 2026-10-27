<?php

use App\Billing\BillingEngine;
use App\Livewire\Extras;
use App\Models\BillingPeriod;
use App\Models\Extra;
use App\Models\HourLine;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Project::create(['job_number' => '#1', 'job_name' => 'One', 'developer' => 'Acme', 'active' => true]);
    Project::create(['job_number' => '#2', 'job_name' => 'Two', 'active' => true]);

    BillingPeriod::create(['period' => '2026-05-31', 'status' => 'closed']);
    BillingPeriod::create(['period' => '2026-06-30', 'status' => 'open']);

    DB::table('hour_lines')->insert([
        ['period' => '2026-06-30', 'job_number' => '#1', 'staff' => 'Adam', 'hours' => 10, 'rate' => 150, 'amount' => null],
        ['period' => '2026-06-30', 'job_number' => '#1', 'staff' => 'Beth', 'hours' => 2, 'rate' => 120, 'amount' => 0],
        ['period' => '2026-06-30', 'job_number' => '#1', 'staff' => 'Beth', 'hours' => null, 'rate' => null, 'amount' => 300],
    ]);

    DB::table('month_hourly')->insert([
        ['period' => '2026-06-30', 'job_number' => '#1', 'amount' => 1400],
        ['period' => '2026-06-30', 'job_number' => '#2', 'amount' => 500],
    ]);
});

it('rates the lines with hours over those hours and keeps written-off time in the hours', function () {
    $extras = app(BillingEngine::class)->extras();

    $june = collect($extras['months'])->firstWhere('period', '2026-06-30');
    $cells = collect($extras['jobMonths'])->keyBy('jobNumber');

    expect($cells['#1'])->toMatchArray(['kind' => 'adjusted', 'hours' => 12.0, 'billed' => '1400.00', 'linesTotal' => '1800.00', 'rate' => 125.0, 'writtenOffHours' => 2.0])
        ->and($cells['#2'])->toMatchArray(['kind' => 'flat', 'hours' => 0.0, 'billed' => '500.00', 'rate' => null])
        ->and($june)->toMatchArray([
            'hours' => 12.0,
            'billed' => '1900.00',
            'linesTotal' => '1800.00',
            'flat' => '500.00',
            'adjust' => '-400.00',
            'rate' => 125.0,
            'writtenOffHours' => 2.0,
            'jobs' => 2,
            'people' => 2,
        ])
        ->and(collect($extras['months'])->pluck('period')->all())->toBe(['2026-06-30']);
});

it('is closed to staff without the financials permission', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('extras'))
        ->assertForbidden();
});

function extrasPull(): string
{
    $folder = sys_get_temp_dir().'/mosaic-extras-'.uniqid();
    mkdir($folder);

    $write = fn (string $name, array $payload) => file_put_contents("{$folder}/{$name}.json", json_encode($payload));

    $write('project-100', ['project' => [['mosaic_id' => 500, 'project_number' => '#1'], ['mosaic_id' => 501, 'project_number' => '#9']]]);
    $write('phase_index', ['phase' => [
        ['mosaic_id' => 1, 'project_id' => 500, 'name' => '1001-02: Sales centre', 'total' => '4500.0', 'budget_status' => 'active'],
        ['mosaic_id' => 2, 'project_id' => 500, 'name' => 'Construction Administration (Hourly)', 'total' => '0.0', 'budget_status' => 'not started'],
        ['mosaic_id' => 3, 'project_id' => 500, 'name' => 'Schematic Design', 'total' => '20000.0', 'budget_status' => 'complete'],
        ['mosaic_id' => 4, 'project_id' => 501, 'name' => '9-01: Not a project here', 'total' => '100.0', 'budget_status' => 'active'],
    ]]);

    return $folder;
}

it('brings in Mosaic extras once and tags lines whose notes name them', function () {
    DB::table('hour_lines')->where('staff', 'Adam')->update(['note' => 'Extra #1001-02 sales centre plans']);
    DB::table('hour_lines')->where('amount', 0)->update(['note' => 'CA']);

    $this->artisan('mosaic:import-extras', ['path' => extrasPull()])->assertSuccessful();

    expect(Extra::orderBy('mosaic_id')->get(['code', 'title', 'status', 'budget_amount'])->toArray())->toBe([
        ['code' => '1001-02', 'title' => 'Sales centre', 'status' => 'active', 'budget_amount' => '4500.00'],
        ['code' => null, 'title' => 'Construction Administration (Hourly)', 'status' => 'proposed', 'budget_amount' => null],
    ])->and(HourLine::where('staff', 'Adam')->first()->extra_id)->toBe(Extra::where('code', '1001-02')->value('id'))
        ->and(HourLine::where('amount', 0)->first()->extra_id)->toBe(Extra::whereNull('code')->value('id'));

    Extra::where('code', '1001-02')->update(['status' => 'complete']);
    $this->artisan('mosaic:import-extras', ['path' => extrasPull()])->assertSuccessful();

    expect(Extra::count())->toBe(2)->and(Extra::where('code', '1001-02')->value('status'))->toBe('complete');
});

it('adds, changes and removes an extra, untagging its lines when it goes', function () {
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(Extras::class)
        ->call('create', '#1')
        ->set('form.title', '')
        ->call('save')
        ->assertHasErrors('extra')
        ->set('form.title', 'Unit plan re-design')
        ->set('form.code', '1-03')
        ->set('form.budget_amount', '$5,000')
        ->call('save')
        ->assertHasNoErrors();

    $extra = Extra::sole();
    expect($extra->only(['job_number', 'code', 'status', 'budget_amount']))->toBe(['job_number' => '#1', 'code' => '1-03', 'status' => 'active', 'budget_amount' => '5000.00']);

    $component->call('setStatus', $extra->id, 'complete');
    expect($extra->refresh()->status)->toBe('complete');

    $component->call('setStatus', $extra->id, 'rejected')->call('setView', 'rejected')->assertSee('Unit plan re-design');
    expect($extra->refresh()->status)->toBe('rejected');

    $line = HourLine::where('staff', 'Adam')->firstOrFail();
    $component->call('tagLine', $line->id, (string) $extra->id);
    expect($line->refresh()->extra_id)->toBe($extra->id);

    $component->call('edit', $extra->id)->call('delete');
    expect(Extra::count())->toBe(0)->and($line->refresh()->extra_id)->toBeNull();

    foreach (['insert', 'update', 'tag', 'delete'] as $action) {
        $this->assertDatabaseHas('audit_logs', ['action' => $action]);
    }
});

it('tags ticked untagged lines to an extra on their job, or to a new one', function () {
    $this->actingAs(User::factory()->admin()->create());

    $other = Extra::create(['job_number' => '#2', 'title' => 'Elsewhere', 'status' => 'active']);
    [$first, $second] = HourLine::where('job_number', '#1')->orderBy('id')->take(2)->get()->all();

    Livewire::test(Extras::class, ['view' => 'untagged'])
        ->assertSee('One')
        ->call('tagLine', $first->id, (string) $other->id)
        ->assertHasErrors('tag')
        ->set("picked.{$first->id}", true)
        ->set("picked.{$second->id}", true)
        ->call('tagPicked', '#1', 'new')
        ->assertSet('editorOpen', true)
        ->set('form.title', 'Construction Administration')
        ->call('save');

    $extra = Extra::where('title', 'Construction Administration')->sole();
    expect(HourLine::where('extra_id', $extra->id)->pluck('id')->all())->toBe([$first->id, $second->id]);

    Livewire::test(Extras::class)
        ->assertSee('Construction Administration')
        ->assertSee('$1,500');
});

it('needs edit billing to change an extra', function () {
    $extra = Extra::create(['job_number' => '#1', 'title' => 'Sales centre', 'status' => 'active']);
    $viewer = User::factory()->staff()->create();
    $viewer->givePermissionTo('view financials');

    Livewire::actingAs($viewer)
        ->test(Extras::class)
        ->assertSee('Sales centre')
        ->call('setStatus', $extra->id, 'complete')
        ->assertForbidden();
});

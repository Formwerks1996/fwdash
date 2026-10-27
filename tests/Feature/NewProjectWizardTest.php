<?php

use App\Actions\Billing\CreateProjectAction;
use App\Livewire\Projects\ProjectsCreate;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectHourBudget;
use App\Models\ProjectPhase;
use App\Models\ProjectPhaseFee;
use App\Models\ProjectRole;
use App\Models\ProjectWorkPlan;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    DB::table('rate_titles')->insert([
        ['title' => 'Junior', 'default_charge_out' => 145, 'seq' => 0],
        ['title' => 'Intermediate', 'default_charge_out' => 145, 'seq' => 1],
        ['title' => 'Senior', 'default_charge_out' => 195, 'seq' => 2],
        ['title' => 'Principal', 'default_charge_out' => 250, 'seq' => 3],
    ]);

    TeamMember::firstOrCreate(['name' => 'Andrew'], ['tier' => 'partner']);
    TeamMember::firstOrCreate(['name' => 'Julie'], ['tier' => 'pm']);
    TeamMember::firstOrCreate(['name' => 'Kendra'], ['tier' => 'staff', 'grp' => 'tech']);

    $this->actingAs(User::factory()->manager()->create());
});

it('sets up a whole job: phases, fee ladder, rates, team, hours and work plans', function () {
    $wizard = Livewire::test(ProjectsCreate::class)
        ->set('basics.job_number', '8200')
        ->set('basics.job_name', '1710 Macdonald St')
        ->set('basics.developer', 'Macdonald Holdings')
        ->set('basics.division', 'MF')
        ->call('next')
        ->assertSet('step', 2)
        ->set('template', 'single-family')
        ->call('applyTemplate')
        ->set('totalFee', '100000')
        ->call('splitFee')
        ->call('next')
        ->set('schedule', '100-125-150-225')
        ->call('next')
        ->set('basics.lead', 'Andrew')
        ->set('basics.pm', 'Julie')
        ->call('addMember', 'Kendra')
        ->assertSet('team.0.position', 'Technologist')
        ->call('next')
        ->set('phases.0.hours', '40')
        ->set('budgets.0.Kendra', '30')
        ->set('budgets.0.Julie', '10')
        ->call('addPlan')
        ->set('plans.0.member', 'Kendra')
        ->set('plans.0.phase', '0')
        ->set('plans.0.start', '2026-11-02')
        ->set('plans.0.end', '2026-11-13')
        ->set('plans.0.daily', '3')
        ->call('next')
        ->assertSet('step', 6)
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('projects.show', '#8200'));

    $project = Project::findOrFail('#8200');
    expect($project->job_name)->toBe('1710 Macdonald St')
        ->and($project->project_lead)->toBe('Andrew')
        ->and($project->active)->toBeTrue();

    $phases = ProjectPhase::where('job_number', '#8200')->orderBy('seq')->get();
    expect($phases)->toHaveCount(8)
        ->and($phases->first()->name)->toBe('Schematic Design')
        ->and($phases->last()->billing_code)->toBe('Hours')
        ->and($phases->last()->fee)->toBeNull()
        ->and($phases->sum(fn ($phase) => (float) $phase->fee))->toBe(100000.0);

    // Single family bills two phases under DD to DP: their fees are added together.
    $ddToDp = $phases->where('billing_code', 'DD to DP')->sum(fn ($phase) => (float) $phase->fee);
    expect((float) ProjectPhaseFee::where(['job_number' => '#8200', 'phase_code' => 'DD to DP'])->sole()->fee)->toBe($ddToDp);

    expect(DB::table('project_rate_overrides')->where('job_number', '#8200')->pluck('rate', 'rate_title')->map(fn ($rate) => (float) $rate)->all())
        ->toEqualCanonicalizing(['Junior' => 100.0, 'Intermediate' => 125.0, 'Senior' => 150.0, 'Principal' => 225.0]);

    expect(ProjectRole::byJob(['#8200'])['#8200'])->toBe([ProjectRole::Lead => ['Andrew'], ProjectRole::ProjectManager => ['Julie']])
        ->and(ProjectAssignment::where('job_number', '#8200')->pluck('position', 'member_name')->all())
        ->toEqualCanonicalizing(['Julie' => 'Project Manager', 'Kendra' => 'Technologist']);

    expect((float) $phases->first()->estimated_hours)->toBe(40.0)
        ->and(ProjectHourBudget::where('project_phase_id', $phases->first()->id)->pluck('hours', 'member_name')->map(fn ($hours) => (float) $hours)->all())
        ->toEqualCanonicalizing(['Kendra' => 30.0, 'Julie' => 10.0]);

    $plan = ProjectWorkPlan::sole();
    expect($plan->project_phase_id)->toBe($phases->first()->id)
        ->and($plan->totalHours())->toBe(30.0);

    // The redirect lands in a fresh request, with a fresh billing engine.
    app()->forgetScopedInstances();
    $this->get(route('projects.show', '#8200'))->assertOk()->assertSee('Phases &amp; hours', false)->assertSee('Construction Detailing');
});

it('stops on the basics step when the job number is taken', function () {
    Project::create(['job_number' => '#8200', 'job_name' => 'Hillcrest']);

    Livewire::test(ProjectsCreate::class)
        ->set('basics.job_number', '8200')
        ->set('basics.job_name', 'Another job')
        ->call('next')
        ->assertHasErrors('basics.job_number')
        ->assertSet('step', 1);
});

it('splits the total fee by the usual shares, to the dollar', function () {
    $wizard = Livewire::test(ProjectsCreate::class)
        ->set('template', 'multi-family')
        ->call('applyTemplate')
        ->set('totalFee', '123457')
        ->call('splitFee');

    $phases = collect($wizard->get('phases'));

    expect($phases->sum(fn ($phase) => (float) $phase['fee']))->toBe(123457.0)
        ->and($phases->firstWhere('code', 'FS')['fee'])->toBe('')
        ->and($phases->firstWhere('code', 'Hours')['fee'])->toBe('');
});

it('re-points hour budgets and work plans when a phase is removed', function () {
    $wizard = Livewire::test(ProjectsCreate::class)
        ->set('template', 'multi-family')
        ->call('applyTemplate')
        ->set('budgets', [1 => ['Kendra' => '10'], 2 => ['Kendra' => '20']])
        ->set('plans', [
            ['member' => 'Kendra', 'phase' => '1', 'start' => '2026-11-02', 'end' => '2026-11-06', 'daily' => '4'],
            ['member' => 'Kendra', 'phase' => '2', 'start' => '2026-11-02', 'end' => '2026-11-06', 'daily' => '4'],
        ])
        ->call('removePhase', 1);

    expect($wizard->get('budgets'))->toBe([1 => ['Kendra' => '20']])
        ->and(collect($wizard->get('plans'))->pluck('phase')->all())->toBe(['', '1'])
        ->and($wizard->get('phases.1.name'))->toBe('Design Development to Development Permit Application');
});

it('copies an existing job’s phases, rates and team', function () {
    Project::create(['job_number' => '#8100', 'job_name' => 'Balfour', 'division' => 'MF', 'developer' => 'Balfour Dev']);
    ProjectPhaseFee::create(['job_number' => '#8100', 'phase_code' => 'SD', 'fee' => 20000]);
    ProjectPhaseFee::create(['job_number' => '#8100', 'phase_code' => 'CA', 'fee' => 15000]);
    DB::table('project_rate_overrides')->insert(['job_number' => '#8100', 'rate_title' => 'Junior', 'rate' => 90]);
    ProjectRole::create(['job_number' => '#8100', 'member_name' => 'Julie', 'role' => 'pm', 'set_by' => 'test', 'set_at' => now()]);
    ProjectAssignment::create(['job_number' => '#8100', 'member_name' => 'Kendra', 'role' => 'member', 'set_by' => 'test', 'set_at' => now()]);

    Livewire::test(ProjectsCreate::class)
        ->set('copyFrom', '#8100')
        ->call('copy')
        ->assertSet('basics.developer', 'Balfour Dev')
        ->assertSet('basics.pm', 'Julie')
        ->assertSet('phases.0.name', 'Schematic Design')
        ->assertSet('phases.0.fee', '20000')
        ->assertSet('phases.1.code', 'CA')
        ->assertSet('phases.2.code', 'Hours')
        ->assertSet('rates.Junior', '90')
        ->assertSet('schedule', 'custom')
        ->assertSet('team', [['member' => 'Kendra', 'position' => 'Technologist']]);
});

it('saves nothing when part of the set-up is invalid', function () {
    expect(fn () => app(CreateProjectAction::class)->execute([
        'job_number' => '#8300',
        'job_name' => 'Half set up',
        'phases' => [['name' => 'Schematic Design', 'code' => 'SD', 'fee' => '1000']],
        'plans' => [['member' => 'Nobody', 'phase' => 0, 'start' => '2026-11-02', 'end' => '2026-11-06', 'daily' => '4']],
    ]))->toThrow(ValidationException::class);

    expect(Project::find('#8300'))->toBeNull()
        ->and(ProjectPhase::count())->toBe(0);
});

it('keeps the wizard to people who can edit billing', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('projects.create'))
        ->assertForbidden();
});

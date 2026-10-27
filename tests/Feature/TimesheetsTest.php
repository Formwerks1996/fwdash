<?php

use App\Actions\Timesheets\SaveTimesheetEntryAction;
use App\Enums\TimeCategory;
use App\Livewire\Timesheets\Timesheet;
use App\Models\Project;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-28 10:00');

    Project::create(['job_number' => '#8016', 'job_name' => 'Seaton', 'active' => true]);
});

it('lets every role open their timesheet', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('timesheets'))->assertOk()->assertSee('Timesheets');
})->with(['admin', 'manager', 'staff']);

it('limits staff to projects and timesheets', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('timesheets'))->assertOk()
        ->assertSee(route('projects.index'))
        ->assertDontSee(route('overview'));

    $this->actingAs($staff)->get(route('overview'))->assertForbidden();
});

it('saves, changes and clears hours in a cell', function () {
    $user = User::factory()->staff()->create();
    $row = TimesheetEntry::makeRowKey(TimeCategory::Project, '#8016', 'SD');

    $component = Livewire::actingAs($user)->test(Timesheet::class)
        ->assertSet('week', '2026-10-26')
        ->call('saveHours', $row, '2026-10-27', '1:30')
        ->assertHasNoErrors();

    $entry = TimesheetEntry::sole();
    expect($entry->user_id)->toBe($user->id)
        ->and($entry->job_number)->toBe('#8016')
        ->and($entry->phase_code)->toBe('SD')
        ->and($entry->hours)->toBe('1.50');

    $component->call('saveHours', $row, '2026-10-27', '7.5');
    expect(TimesheetEntry::sole()->hours)->toBe('7.50');

    $component->call('saveHours', $row, '2026-10-27', '');
    expect(TimesheetEntry::count())->toBe(0);
});

it('keeps a note with the hours', function () {
    $user = User::factory()->staff()->create();
    $row = TimesheetEntry::makeRowKey(TimeCategory::Office, null, null);

    Livewire::actingAs($user)->test(Timesheet::class)
        ->call('editCell', $row, '2026-10-28')
        ->set('cell.hours', '2')
        ->set('cell.note', 'Staff meeting')
        ->call('saveCell')
        ->assertHasNoErrors()
        ->call('saveHours', $row, '2026-10-28', '2.5');

    expect(TimesheetEntry::sole())
        ->hours->toBe('2.50')
        ->note->toBe('Staff meeting')
        ->category->toBe(TimeCategory::Office);
});

it('refuses hours that are not quarter hours, or a day over 24 hours', function () {
    $user = User::factory()->staff()->create();
    $row = TimesheetEntry::makeRowKey(TimeCategory::Office, null, null);

    Livewire::actingAs($user)->test(Timesheet::class)
        ->call('saveHours', $row, '2026-10-27', '1.1')
        ->assertHasErrors(['timesheet' => 'Log time in quarter hours (0.25, 0.5, 0.75).'])
        ->call('saveHours', $row, '2026-10-27', 'abc')
        ->assertHasErrors('timesheet')
        ->call('saveHours', $row, '2026-10-27', '20')
        ->call('saveHours', TimesheetEntry::makeRowKey(TimeCategory::Project, '#8016', null), '2026-10-27', '5')
        ->assertHasErrors('timesheet');

    expect(TimesheetEntry::count())->toBe(1);
});

it('refuses project time for a project that does not exist', function () {
    $user = User::factory()->staff()->create();

    expect(fn () => app(SaveTimesheetEntryAction::class)->execute($user, 'project|#9999|', '2026-10-27', '1'))
        ->toThrow(ValidationException::class);
});

it('only writes to days in the week shown', function () {
    $user = User::factory()->staff()->create();

    Livewire::actingAs($user)->test(Timesheet::class)
        ->call('saveHours', 'office||', '2026-11-04', '1')
        ->assertStatus(422);
});

it('shows a week with its rows and totals against capacity', function () {
    $user = User::factory()->staff()->create();
    TimesheetEntry::factory()->for($user)->forProject('#8016', 'CA')->create(['work_date' => '2026-10-26', 'hours' => 6]);
    TimesheetEntry::factory()->for($user)->create(['work_date' => '2026-10-26', 'hours' => 1.5, 'category' => TimeCategory::Office]);
    TimesheetEntry::factory()->for($user)->create(['work_date' => '2026-10-19', 'hours' => 7.5, 'category' => TimeCategory::Vacation]);

    $component = Livewire::actingAs($user)->test(Timesheet::class);

    expect($component->get('rows')->pluck('key')->all())->toBe(['project|#8016|CA', 'office||'])
        ->and($component->get('totals')['total'])->toBe(7.5)
        ->and($component->get('totals')['capacity'])->toBe(37.5)
        ->and($component->get('totals')['project'])->toBe(6.0);

    $component->assertSee('Seaton')->assertSee('#8016 · CA');
});

it('adds an empty row and copies last week\'s rows', function () {
    $user = User::factory()->staff()->create();
    TimesheetEntry::factory()->for($user)->create(['work_date' => '2026-10-20', 'category' => TimeCategory::Sick]);

    Livewire::actingAs($user)->test(Timesheet::class)
        ->set('newRow', ['category' => 'project', 'job_number' => '#8016', 'phase_code' => 'DPP'])
        ->call('addRow')
        ->assertHasNoErrors()
        ->call('copyPreviousRows')
        ->assertSet('extraRows', ['project|#8016|DPP', 'sick||'])
        ->assertSee('Seaton')
        ->assertSee('Sick');

    expect(TimesheetEntry::count())->toBe(1);
});

it('removes a row and its hours for the week', function () {
    $user = User::factory()->staff()->create();
    TimesheetEntry::factory()->for($user)->forProject('#8016')->count(2)->sequence(['work_date' => '2026-10-26'], ['work_date' => '2026-10-27'])->create();
    TimesheetEntry::factory()->for($user)->forProject('#8016')->create(['work_date' => '2026-10-19']);

    Livewire::actingAs($user)->test(Timesheet::class)->call('removeRow', 'project|#8016|');

    expect(TimesheetEntry::pluck('work_date')->map->toDateString()->all())->toBe(['2026-10-19']);
});

it('lets admins read anyone\'s week but not change it', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create(['name' => 'Zaldy Cortes']);
    TimesheetEntry::factory()->for($staff)->forProject('#8016')->create(['work_date' => '2026-10-27', 'hours' => 4]);

    Livewire::actingAs($admin)->test(Timesheet::class, ['personId' => $staff->id])
        ->assertSee('Zaldy Cortes’s week, read-only.')
        ->assertSee('Seaton')
        ->call('saveHours', 'project|#8016|', '2026-10-27', '8')
        ->assertForbidden();

    expect(TimesheetEntry::sole()->hours)->toBe('4.00');
});

it('keeps managers and staff to their own week', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $other = User::factory()->staff()->create();

    $this->actingAs($user)->get(route('timesheets', ['person' => $other->id]))->assertForbidden();
})->with(['manager', 'staff']);

it('hides other people\'s weeks while an admin previews as a manager', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->staff()->create();

    $this->actingAs($admin)->withSession(['view_as' => 'manager']);

    $this->get(route('timesheets'))->assertOk()->assertDontSee('Whose timesheet');
    $this->get(route('timesheets', ['person' => $other->id]))->assertForbidden();
});

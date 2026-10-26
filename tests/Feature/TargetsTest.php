<?php

use App\Actions\Billing\SetMonthlyTargetAction;
use App\Enums\Permission;
use App\Livewire\Targets;
use App\Models\MonthlyTarget;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('renders the page', function () {
    MonthlyTarget::create(['month' => '2026-03', 'amount' => 250000]);

    $this->get(route('targets', ['year' => 2026]))
        ->assertOk()
        ->assertSee('Monthly Targets')
        ->assertSee('250,000');
});

it('reads an amount however it is typed', function (string $typed) {
    Livewire::test(Targets::class, ['year' => 2026])
        ->call('saveTarget', '2026-02', $typed)
        ->assertHasNoErrors();

    expect(MonthlyTarget::find('2026-02')->amount)->toBe('300000.00');
})->with(['300,000', '300k', '$300,000']);

it('refuses something that is not an amount', function () {
    Livewire::test(Targets::class, ['year' => 2026])
        ->call('saveTarget', '2026-02', 'abc')
        ->assertHasErrors(['target' => '"abc" isn\'t an amount.']);

    expect(MonthlyTarget::count())->toBe(0);
});

it('refuses a negative target', function () {
    app(SetMonthlyTargetAction::class)->execute('2026-02', '-5');
})->throws(ValidationException::class, 'A target can\'t be negative.');

it('keeps the note when the amount changes and logs a clear', function () {
    MonthlyTarget::create(['month' => '2026-04', 'amount' => 100000, 'note' => 'Big CA month']);

    Livewire::test(Targets::class, ['year' => 2026])
        ->call('saveTarget', '2026-04', '120k');

    expect(MonthlyTarget::find('2026-04'))->amount->toBe('120000.00')->note->toBe('Big CA month');

    Livewire::test(Targets::class, ['year' => 2026])
        ->call('saveTarget', '2026-04', '');

    expect(MonthlyTarget::find('2026-04'))->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'target.clear', 'table_name' => 'monthly_targets', 'record_key' => '2026-04']);
});

it('fills every month with one change-log row each', function () {
    MonthlyTarget::create(['month' => '2026-01', 'amount' => 1]);

    Livewire::test(Targets::class, ['year' => 2026])
        ->set('fillAmount', '300k')
        ->call('fillMonths', true);

    expect(MonthlyTarget::where('amount', 300000)->count())->toBe(12);
    $this->assertDatabaseCount('audit_logs', 12);
});

it('fills only the empty months', function () {
    MonthlyTarget::create(['month' => '2026-01', 'amount' => 1]);

    Livewire::test(Targets::class, ['year' => 2026])
        ->set('fillAmount', '300k')
        ->call('fillMonths', false);

    expect(MonthlyTarget::find('2026-01')->amount)->toBe('1.00')
        ->and(MonthlyTarget::where('amount', 300000)->count())->toBe(11);
});

it('lets a viewer read targets but not set them', function () {
    $viewer = User::factory()->staff()->create();
    $viewer->givePermissionTo(Permission::ViewFinancials->value);

    $this->actingAs($viewer)->get(route('targets'))->assertOk();

    Livewire::actingAs($viewer)
        ->test(Targets::class, ['year' => 2026])
        ->call('saveTarget', '2026-02', '300k')
        ->assertForbidden();

    expect(MonthlyTarget::count())->toBe(0);
});

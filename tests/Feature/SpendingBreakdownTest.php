<?php

use App\Enums\Permission;
use App\Livewire\SpendingBreakdown;
use App\Models\AppSetting;
use App\Models\MonthlyCost;
use App\Models\StaffCostRate;
use App\Models\StaffMonthHours;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * May and June costs on the sheet, and Pat's hours over both months with a
 * raise in June; Sam has hours but no rate.
 */
beforeEach(function () {
    MonthlyCost::insert([
        ['month' => '2026-05', 'category' => 'Payroll', 'amount' => 8000, 'seq' => 1],
        ['month' => '2026-05', 'category' => 'Rent', 'amount' => 2000, 'seq' => 2],
        ['month' => '2026-06', 'category' => 'Payroll', 'amount' => 9000, 'seq' => 1],
        ['month' => '2026-06', 'category' => 'Rent', 'amount' => 2000, 'seq' => 2],
        ['month' => '2026-06', 'category' => 'Software', 'amount' => 1000, 'seq' => 3],
    ]);
    DB::table('monthly_cost_notes')->insert(['month' => '2026-06', 'note' => 'new licences']);

    AppSetting::create(['key' => 'mosaic.hours_through', 'value' => '2026-06']);
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'name' => 'Pat Lee', 'hourly_cost' => 50, 'starts_on' => '2026-01-01']);
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'name' => 'Pat Lee', 'hourly_cost' => 60, 'starts_on' => '2026-06-01']);

    StaffMonthHours::insert([
        ['email' => 'pat@formwerks.ca', 'name' => 'Pat Lee', 'month' => '2026-05', 'hours' => 100, 'source' => 'mosaic'],
        ['email' => 'pat@formwerks.ca', 'name' => 'Pat Lee', 'month' => '2026-06', 'hours' => 100, 'source' => 'mosaic'],
        ['email' => 'sam@formwerks.ca', 'name' => 'Sam Roe', 'month' => '2026-06', 'hours' => 20, 'source' => 'mosaic'],
    ]);
});

it('breaks the months down by cost line and by person', function () {
    $this->actingAs(User::factory()->admin()->create());

    $breakdown = Livewire::test(SpendingBreakdown::class)
        ->dispatch('show-spending', months: ['2026-06', '2026-05'])
        ->assertSet('open', true)
        ->assertSee(['Where the money went — May – Jun 2026', 'Software', 'new licences', 'Pat Lee', '20 h are by people with no cost rate'])
        ->instance()->breakdown;

    expect(collect($breakdown['categories'])->pluck('amount', 'category')->all())->toBe(['Payroll' => 17000.0, 'Rent' => 4000.0, 'Software' => 1000.0])
        ->and($breakdown['costsTotal'])->toBe(22000.0)
        ->and(collect($breakdown['months'])->pluck('staff', 'key')->all())->toBe(['2026-05' => 5000.0, '2026-06' => 6000.0])
        ->and($breakdown['staff'])->toMatchArray(['total' => 11000.0, 'hours' => 220.0, 'uncostedHours' => 20.0])
        ->and($breakdown['staff']['people'][0])->toMatchArray(['name' => 'Pat Lee', 'hours' => 200.0, 'cost' => 11000.0, 'rate' => 55.0])
        ->and($breakdown['staff']['people'][1])->toMatchArray(['name' => 'Sam Roe', 'cost' => null]);
});

it('leaves out staff by person for those who do not manage staff costs', function () {
    $user = User::factory()->staff()->create();
    $user->givePermissionTo(Permission::ViewFinancials->value);
    $this->actingAs($user);

    Livewire::test(SpendingBreakdown::class)
        ->dispatch('show-spending', months: ['2026-06'])
        ->assertSee('Software')
        ->assertDontSee('Pat Lee');
});

it('is closed to staff without the financials permission', function () {
    $this->actingAs(User::factory()->staff()->create());

    Livewire::test(SpendingBreakdown::class)
        ->dispatch('show-spending', months: ['2026-06'])
        ->assertForbidden();
});

it('is on Overview, opened from the Cost card', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('overview'))->assertSeeLivewire(SpendingBreakdown::class)->assertSee('show-spending');
});

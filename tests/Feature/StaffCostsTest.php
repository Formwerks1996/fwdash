<?php

use App\Billing\StaffCosts as Costs;
use App\Livewire\MonthEnd\MonthEndReport;
use App\Livewire\StaffCosts;
use App\Models\AppSetting;
use App\Models\BillingPeriod;
use App\Models\StaffCostRate;
use App\Models\StaffMonthHours;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function mosaicCostPull(): string
{
    $folder = sys_get_temp_dir().'/mosaic-costs-'.uniqid();
    mkdir($folder);

    $write = fn (string $name, array $payload) => file_put_contents("{$folder}/{$name}.json", json_encode($payload));

    $write('employee', ['employee' => [
        ['mosaic_id' => 1, 'email' => 'Pat@Formwerks.ca', 'first_name' => 'Pat', 'last_name' => 'Lee'],
        ['mosaic_id' => 2, 'email' => 'sam@formwerks.ca', 'first_name' => 'Sam', 'last_name' => 'Roe'],
        ['mosaic_id' => 3, 'email' => 'priorhours@formwerks.ca', 'first_name' => 'Prior', 'last_name' => 'Hours'],
    ]]);
    $write('cost_rate', ['cost_rates' => [
        ['member_id' => 1, 'actual_hourly_rate' => 80.5, 'start_date' => '2026-01-01', 'discarded' => false],
        ['member_id' => 3, 'actual_hourly_rate' => 50, 'start_date' => '2022-01-01', 'discarded' => false],
    ]]);
    $write('time_entry-2026-03', ['time_entry' => [
        ['member_email' => 'pat@formwerks.ca', 'member_name' => 'Pat Lee', 'date' => '2026-03-02', 'hours' => '7.5', 'status' => 'approved'],
        ['member_email' => 'pat@formwerks.ca', 'member_name' => 'Pat Lee', 'date' => '2026-03-03', 'hours' => '2.5', 'status' => 'not_submitted'],
        ['member_email' => 'pat@formwerks.ca', 'member_name' => 'Pat Lee', 'date' => '2026-03-04', 'hours' => '9', 'status' => 'rejected'],
        ['member_email' => 'sam@formwerks.ca', 'member_name' => 'Sam Roe', 'date' => '2026-03-02', 'hours' => '4', 'status' => 'approved'],
        ['member_email' => 'priorhours@formwerks.ca', 'member_name' => 'Prior Hours', 'date' => '2026-03-31', 'hours' => '900', 'status' => 'approved'],
    ]]);

    return $folder;
}

it('imports cost rates and monthly hours from a Mosaic pull', function () {
    $this->artisan('mosaic:import-costs', ['path' => mosaicCostPull()])
        ->expectsOutputToContain('Imported 1 cost rates and 2 months of hours for 2 people, through 2026-03.')
        ->assertSuccessful();

    expect(StaffCostRate::sole()->only(['email', 'name', 'hourly_cost']))->toBe(['email' => 'pat@formwerks.ca', 'name' => 'Pat Lee', 'hourly_cost' => '80.50'])
        ->and(StaffMonthHours::orderBy('email')->pluck('hours', 'email')->all())->toBe(['pat@formwerks.ca' => '10.00', 'sam@formwerks.ca' => '4.00'])
        ->and(AppSetting::find('mosaic.hours_through')->value)->toBe('2026-03');
});

it('keeps a rate set in the dashboard when the pull is imported again', function () {
    $pull = mosaicCostPull();
    $this->artisan('mosaic:import-costs', ['path' => $pull])->assertSuccessful();
    StaffCostRate::sole()->update(['hourly_cost' => 95]);

    $this->artisan('mosaic:import-costs', ['path' => $pull])->assertSuccessful();

    expect(StaffCostRate::sole()->hourly_cost)->toBe('95.00')
        ->and(StaffMonthHours::count())->toBe(2);
});

it('refuses a folder that is not a Mosaic pull', function () {
    $this->artisan('mosaic:import-costs', ['path' => sys_get_temp_dir().'/nowhere-'.uniqid()])->assertFailed();
});

it('prices each month\'s hours at the rate in force, the first rate covering earlier months', function () {
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'hourly_cost' => 80, 'starts_on' => '2026-01-01']);
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'hourly_cost' => 100, 'starts_on' => '2026-03-01']);
    AppSetting::create(['key' => 'mosaic.hours_through', 'value' => '2026-03']);

    foreach (['2025-12' => 10, '2026-02' => 10, '2026-03' => 10] as $month => $hours) {
        StaffMonthHours::create(['email' => 'pat@formwerks.ca', 'month' => $month, 'hours' => $hours, 'source' => 'mosaic']);
    }

    StaffMonthHours::create(['email' => 'sam@formwerks.ca', 'month' => '2026-03', 'hours' => 6, 'source' => 'mosaic']);

    $costs = new Costs;

    expect($costs->month('2025-12'))->toBe(['spent' => 800.0, 'hours' => 10.0, 'uncostedHours' => 0.0])
        ->and($costs->month('2026-02')['spent'])->toBe(800.0)
        ->and($costs->month('2026-03'))->toBe(['spent' => 1000.0, 'hours' => 16.0, 'uncostedHours' => 6.0])
        ->and($costs->month('2026-04')['spent'])->toBeNull();
});

it('counts timesheet hours for the months after the Mosaic pull', function () {
    AppSetting::create(['key' => 'mosaic.hours_through', 'value' => '2026-09']);
    $user = User::factory()->staff()->create(['email' => 'Pat@formwerks.ca']);
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'hourly_cost' => 50, 'starts_on' => '2026-01-01']);
    StaffMonthHours::create(['email' => 'pat@formwerks.ca', 'month' => '2026-10', 'hours' => 99, 'source' => 'mosaic']);
    TimesheetEntry::factory()->for($user)->create(['work_date' => '2026-10-05', 'hours' => '7.50']);
    TimesheetEntry::factory()->for($user)->create(['work_date' => '2026-10-31', 'hours' => '2.50']);
    TimesheetEntry::factory()->for($user)->create(['work_date' => '2026-11-01', 'hours' => '8.00']);

    expect((new Costs)->month('2026-10'))->toBe(['spent' => 500.0, 'hours' => 10.0, 'uncostedHours' => 0.0]);
});

it('starts a changed rate this month and leaves the months before it alone', function () {
    CarbonImmutable::setTestNow('2026-10-15');
    $this->actingAs(User::factory()->manager()->create());
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'name' => 'Pat Lee', 'hourly_cost' => 80, 'starts_on' => '2026-01-01']);

    Livewire::test(StaffCosts::class)
        ->call('setRate', 'pat@formwerks.ca', '$90')
        ->call('setRate', 'pat@formwerks.ca', '92.5')
        ->assertHasNoErrors();

    $costs = new Costs;

    expect(StaffCostRate::count())->toBe(2)
        ->and($costs->rateOn('pat@formwerks.ca', '2026-09'))->toBe(80.0)
        ->and($costs->rateOn('pat@formwerks.ca', '2026-10'))->toBe(92.5);
});

it('gives someone with no rate their first one', function () {
    CarbonImmutable::setTestNow('2026-10-15');
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(StaffCosts::class)->call('setRate', 'New@Formwerks.ca', '60')->assertHasNoErrors();

    expect((new Costs)->rateOn('new@formwerks.ca', '2025-01'))->toBe(60.0);
});

it('refuses something that is not an hourly cost', function (string $typed) {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(StaffCosts::class)
        ->call('setRate', 'pat@formwerks.ca', $typed)
        ->assertHasErrors('rate');

    expect(StaffCostRate::count())->toBe(0);
})->with(['abc', '-5', '0']);

it('lists who costs what, flagging hours with no rate', function () {
    CarbonImmutable::setTestNow('2026-10-15');
    $this->actingAs(User::factory()->manager()->create());
    AppSetting::create(['key' => 'mosaic.hours_through', 'value' => '2026-09']);
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'name' => 'Pat Lee', 'hourly_cost' => 80, 'starts_on' => '2026-01-01']);
    StaffMonthHours::create(['email' => 'pat@formwerks.ca', 'name' => 'Pat Lee', 'month' => '2026-09', 'hours' => 100, 'source' => 'mosaic']);
    StaffMonthHours::create(['email' => 'kim@rlai.com', 'name' => 'Kim Ito', 'month' => '2026-09', 'hours' => 40, 'source' => 'mosaic']);

    $this->get(route('staff-costs'))
        ->assertOk()
        ->assertSee('Pat Lee')
        ->assertSee('$8k')
        ->assertSee('Kim Ito')
        ->assertSee('not costed');
});

it('shows spent on Month End\'s revenue chart for managers', function () {
    $this->actingAs(User::factory()->manager()->create());
    BillingPeriod::create(['period' => '2026-03-31', 'status' => 'open']);
    AppSetting::create(['key' => 'mosaic.hours_through', 'value' => '2026-03']);
    StaffCostRate::create(['email' => 'pat@formwerks.ca', 'hourly_cost' => 80, 'starts_on' => '2026-01-01']);
    StaffMonthHours::create(['email' => 'pat@formwerks.ca', 'month' => '2026-03', 'hours' => 10, 'source' => 'mosaic']);

    $chart = Livewire::test(MonthEndReport::class)->get('figures')['chart'];

    expect($chart['spent'][2])->toBe(800.0)
        ->and($chart['spent'][0])->toBeNull();
});

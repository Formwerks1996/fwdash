<?php

namespace App\Livewire;

use App\Billing\StaffCosts as Costs;
use App\Enums\Permission;
use App\Models\StaffCostRate;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * What each person costs the firm an hour, for managers and admins. The rates
 * price Month End's "Spent" line. Changing one starts a new rate this month,
 * so the months before it keep what they cost.
 */
/**
 * @property-read array<int, string> $window
 * @property-read Collection<int, array<string, mixed>> $people
 * @property-read Collection<int, array<string, mixed>> $rows
 * @property-read array<string, mixed> $totals
 */
#[Title('Staff Costs')]
class StaffCosts extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public bool $everyone = false;

    /** @return array<int, string> The last twelve finished months, oldest first. */
    #[Computed]
    public function window(): array
    {
        $last = CarbonImmutable::today()->startOfMonth()->subMonth();

        return collect(range(11, 0))->map(fn (int $back) => $last->subMonths($back)->format('Y-m'))->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function people(): Collection
    {
        $costs = app(Costs::class);
        $people = [];

        $add = function (string $email, ?string $name) use (&$people) {
            $people[$email] ??= ['email' => $email, 'name' => $name, 'hours' => 0.0, 'cost' => 0.0, 'lastMonth' => null, 'user' => false];
            $people[$email]['name'] ??= $name;
        };

        foreach ($costs->rates() as $email => $rates) {
            $add($email, $rates->last()->name);
        }

        foreach (User::get(['name', 'email']) as $user) {
            $add(Str::lower($user->email), $user->name);
            $people[Str::lower($user->email)]['user'] = true;
        }

        foreach ($this->window as $month) {
            foreach ($costs->hoursIn($month) as $email => $person) {
                $add($email, $person['name']);
                $people[$email]['hours'] += $person['hours'];
                $people[$email]['cost'] += $person['hours'] * ($costs->rateOn($email, $month) ?? 0);
                $people[$email]['lastMonth'] = $month;
            }
        }

        return collect($people)
            ->map(function (array $person) use ($costs) {
                $current = $costs->rates()->get($person['email'])?->last();

                return [
                    ...$person,
                    'name' => $person['name'] ?? $person['email'],
                    'rate' => $current ? (float) $current->hourly_cost : null,
                    'since' => $current?->starts_on,
                    'active' => $person['hours'] > 0 || $person['user'],
                ];
            })
            ->sortBy(fn (array $person) => Str::lower($person['name']))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        $search = Str::lower(trim($this->search));

        return $this->people
            ->when(! $this->everyone, fn (Collection $people) => $people->where('active', true))
            ->when($search !== '', fn (Collection $people) => $people->filter(
                fn (array $person) => Str::contains(Str::lower("{$person['name']} {$person['email']}"), $search),
            ))
            ->values();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function totals(): array
    {
        $active = $this->people->where('active', true);
        $costed = $active->whereNotNull('rate');
        $spent = (float) $this->people->sum('cost');
        $uncosted = $this->people->whereNull('rate')->where('hours', '>', 0);
        $costedHours = (float) $this->people->whereNotNull('rate')->sum('hours');

        return [
            'spent' => $spent,
            'monthly' => $spent / 12,
            'average' => $costedHours ? $spent / $costedHours : null,
            'costed' => $costed->count(),
            'active' => $active->count(),
            'uncostedPeople' => $uncosted->count(),
            'uncostedHours' => (float) $uncosted->sum('hours'),
        ];
    }

    public function setRate(string $email, ?string $value, AuditLogger $audit): void
    {
        $this->authorize(Permission::ManageStaffCosts->value);
        $this->resetErrorBag();

        $email = Str::lower(trim($email));
        $typed = trim(str_replace([',', '$'], '', (string) $value));

        if ($typed === '') {
            return;
        }

        if (! is_numeric($typed) || (float) $typed <= 0) {
            $this->addError('rate', __('":value" isn\'t an hourly cost.', ['value' => $value]));

            return;
        }

        $amount = round((float) $typed, 2);
        $person = $this->people->firstWhere('email', $email);
        $current = app(Costs::class)->rates()->get($email)?->last();

        if ($current && (float) $current->hourly_cost === $amount) {
            return;
        }

        $startsOn = CarbonImmutable::today()->startOfMonth();

        DB::transaction(function () use ($email, $amount, $person, $current, $startsOn, $audit) {
            $rate = StaffCostRate::where('email', $email)->whereDate('starts_on', $startsOn)->first()
                ?? new StaffCostRate(['email' => $email, 'starts_on' => $startsOn]);

            $rate->fill(['name' => $person['name'] ?? $current?->name, 'hourly_cost' => $amount])->save();

            $audit->log('staff_cost.set', 'staff_cost_rates', $rate->id, before: $current?->only(['email', 'hourly_cost', 'starts_on']), after: $rate->only(['email', 'hourly_cost', 'starts_on']));
        });

        unset($this->people, $this->rows, $this->totals);
    }

    public function render(Costs $costs): View
    {
        return view('livewire.staff-costs', [
            'canEdit' => auth()->user()->can(Permission::ManageStaffCosts->value),
            'mosaicThrough' => $costs->mosaicThrough(),
        ]);
    }
}

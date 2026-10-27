<?php

namespace App\Billing;

use App\Models\AppSetting;
use App\Models\StaffCostRate;
use App\Models\StaffMonthHours;
use App\Models\TimesheetEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/*
 * What the firm spent on its people each month: every person's hours that
 * month at their hourly cost. Hours come from the Mosaic pull up to the last
 * month it covers, and from the dashboard's timesheets after that. A rate runs
 * from its start date to the next one; a person's first rate also covers the
 * months before it, since Mosaic keeps only the current rate. Hours by someone
 * with no rate are counted but not costed.
 */
class StaffCosts
{
    /** @var Collection<string, Collection<int, StaffCostRate>>|null */
    protected ?Collection $rates = null;

    /** @var array<string, Collection<string, array{email: string, name: ?string, hours: float}>> */
    protected array $hoursCache = [];

    /** @return Collection<string, Collection<int, StaffCostRate>> Each person's rates, oldest first. */
    public function rates(): Collection
    {
        return $this->rates ??= StaffCostRate::orderBy('starts_on')
            ->get()
            ->groupBy('email');
    }

    public function rateOn(string $email, string $month): ?float
    {
        $rates = $this->rates()->get($email);

        if (! $rates) {
            return null;
        }

        $rate = $rates->last(fn (StaffCostRate $rate) => $rate->starts_on->format('Y-m') <= $month) ?? $rates->first();

        return (float) $rate->hourly_cost;
    }

    /** The last month Mosaic's hours cover; timesheets count from the month after. */
    public function mosaicThrough(): ?string
    {
        return AppSetting::find('mosaic.hours_through')?->value;
    }

    /**
     * Hours per person in one month, keyed by email.
     *
     * @return Collection<string, array{email: string, name: ?string, hours: float}>
     */
    public function hoursIn(string $month): Collection
    {
        if (isset($this->hoursCache[$month])) {
            return $this->hoursCache[$month];
        }

        $through = $this->mosaicThrough();

        if ($through !== null && $month <= $through) {
            $hours = StaffMonthHours::where('month', $month)
                ->where('source', 'mosaic')
                ->get()
                ->mapWithKeys(fn (StaffMonthHours $row) => [$row->email => ['email' => $row->email, 'name' => $row->name, 'hours' => (float) $row->hours]]);
        } else {
            $start = CarbonImmutable::createFromFormat('!Y-m', $month);

            $hours = TimesheetEntry::with('user:id,name,email')
                ->whereBetween('work_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
                ->get()
                ->groupBy(fn (TimesheetEntry $entry) => Str::lower($entry->user->email))
                ->map(fn (Collection $entries, string $email) => ['email' => $email, 'name' => $entries->first()->user->name, 'hours' => (float) $entries->sum('hours')]);
        }

        return $this->hoursCache[$month] = $hours;
    }

    /** @return array{spent: ?float, hours: float, uncostedHours: float} */
    public function month(string $month): array
    {
        $hours = $this->hoursIn($month);
        $costed = $hours->filter(fn (array $person) => $this->rateOn($person['email'], $month) !== null);

        return [
            'spent' => $costed->isEmpty() ? null : round($costed->sum(fn (array $person) => $person['hours'] * $this->rateOn($person['email'], $month)), 2),
            'hours' => (float) $hours->sum('hours'),
            'uncostedHours' => (float) $hours->diffKeys($costed)->sum('hours'),
        ];
    }

    /**
     * Twelve months of spend, January first.
     *
     * @return array<int, array{key: string, spent: ?float, hours: float, uncostedHours: float}>
     */
    public function year(int $year): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month) => ['key' => sprintf('%d-%02d', $year, $month), ...$this->month(sprintf('%d-%02d', $year, $month))])
            ->all();
    }
}

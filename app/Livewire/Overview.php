<?php

namespace App\Livewire;

use App\Billing\BillingEngine;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * How the year is going, from billing alone.
 *
 * The old Overview priced Mosaic time to estimate unbilled months and set
 * payroll against revenue. Without Mosaic only billed, collected and hours
 * remain, so months nobody has run month end on are shown at the run rate and
 * labelled as such, and nothing claims to be profit.
 */
/**
 * @property-read Collection<int, int> $years
 * @property-read array<string, mixed> $figures
 */
#[Title('Overview')]
class Overview extends Component
{
    /** Revenue categories, matched on project division. "Other" catches the rest, blanks included. */
    protected const Divisions = [
        'Multi-family' => ['MF'],
        'Single family' => ['SF'],
        'Interior' => ['INT', 'INT-MF'],
        'Multiplex / CHC' => ['MPX', 'CHC'],
        'Renovation' => ['REN'],
        'Other' => [],
    ];

    protected const Slots = ['#00263E', '#b5654a', '#6f8f72', '#85754E', '#6d5a7b', '#3d5a40'];

    #[Url]
    public ?int $year = null;

    public function mount(): void
    {
        $this->year ??= $this->years()->first() ?? (int) now()->format('Y');
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    /** Years with more than one billed period; 2020 is a single opening-balance lump. */
    /** @return Collection<int, int> */
    #[Computed]
    public function years(): Collection
    {
        return collect(app(BillingEngine::class)->periods())
            ->countBy(fn (string $period) => (int) substr($period, 0, 4))
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->sortDesc()
            ->values();
    }

    /** @return Collection<int, array{key: string, month: int, label: string, period: ?string, billed: ?float, hourlyBilled: ?float, jobCount: int, collected: ?float, consultants: ?float, hours: ?float}> */
    protected function series(int $year): Collection
    {
        $engine = app(BillingEngine::class);

        $periods = collect($engine->periods())->keyBy(fn (string $period) => substr($period, 0, 7));
        $collected = $engine->paymentsByMonth();
        $consultants = $engine->consultantsByMonth();
        $hours = collect($engine->hoursByPeriod())->mapWithKeys(fn (float $value, string $period) => [substr($period, 0, 7) => $value]);

        return collect(range(1, 12))->map(function (int $month) use ($year, $periods, $collected, $consultants, $hours, $engine) {
            $start = CarbonImmutable::create($year, $month, 1);
            $key = $start->format('Y-m');
            $period = $periods[$key] ?? null;
            $totals = $period ? $engine->periodTotals($period) : null;

            return [
                'key' => $key,
                'month' => $month,
                'label' => $start->format('M'),
                'period' => $period,
                'billed' => $totals ? (float) $totals['billed'] : null,
                'hourlyBilled' => $totals ? (float) $totals['hourlyBilled'] : null,
                'jobCount' => $totals['jobCount'] ?? 0,
                'collected' => isset($collected[$key]) && (float) $collected[$key] ? (float) $collected[$key] : null,
                'consultants' => isset($consultants[$key]) ? (float) $consultants[$key] : null,
                'hours' => $hours[$key] ?? null,
            ];
        });
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function figures(): array
    {
        $engine = app(BillingEngine::class);
        $months = $this->series($this->year);
        $prior = $this->series($this->year - 1);

        $entered = $months->whereNotNull('billed');
        $lastEntered = $entered->last();
        $upTo = $lastEntered['month'] ?? 12;
        $priorSpan = $prior->whereNotNull('billed')->where('month', '<=', $upTo);

        $ytd = $entered->sum('billed');
        $priorYtd = $entered->isNotEmpty() && $priorSpan->count() === $entered->count()
            ? $priorSpan->sum('billed')
            : null;

        $runRate = $entered->isNotEmpty() ? $ytd / $entered->count() : 0;

        // Months after the last one entered are projected at the run rate, in the current year only.
        $projectedMonths = $this->year === (int) now()->format('Y')
            ? $months->whereNull('billed')->where('month', '>', $lastEntered['month'] ?? 0)
            : collect();

        $rated = $entered->filter(fn (array $month) => (bool) $month['hours']);
        $realized = $rated->sum('hours') ? $rated->sum('billed') / $rated->sum('hours') : null;
        $priorRated = $prior->whereNotNull('billed')->filter(fn (array $month) => $month['hours'] && $month['month'] <= $upTo);
        $priorRealized = $priorRated->count() === $rated->count() && $priorRated->sum('hours')
            ? $priorRated->sum('billed') / $priorRated->sum('hours')
            : null;

        $active = collect($engine->allSummaries())->where('active', true);
        $remaining = $active->filter(fn (array $row) => Money::compare($row['balance'], 0) > 0);

        $billedByJob = collect($engine->billedByJobPeriod())
            ->filter(fn (string $amount, string $key) => str_starts_with($key, (string) $this->year))
            ->groupBy(fn (string $amount, string $key) => explode('|', $key, 2)[1], preserveKeys: true)
            ->map(fn (Collection $amounts) => (float) Money::sum($amounts));

        $divisions = collect(self::Divisions)->map(fn () => 0.0)->all();

        foreach ($billedByJob as $jobNumber => $amount) {
            $code = $engine->job($jobNumber)['division'] ?? null;
            $category = collect(self::Divisions)->search(fn (array $codes) => in_array($code, $codes, true));
            $divisions[$category ?: 'Other'] += $amount;
        }

        $hoursLoaded = collect($engine->hoursByPeriod())->keys()->last();

        return [
            'months' => $months,
            'monthsEntered' => $entered->count(),
            'lastEntered' => $lastEntered['period'] ?? null,
            'hoursLoaded' => $hoursLoaded,
            'ytd' => $ytd,
            'priorYtd' => $priorYtd,
            'priorFull' => $prior->whereNotNull('billed')->count() === 12 ? $prior->sum('billed') : null,
            'runRate' => $runRate,
            'projected' => $ytd + $runRate * $projectedMonths->count(),
            'realized' => $realized,
            'realizedHours' => $rated->sum('hours'),
            'priorRealized' => $priorRealized,
            'collected' => $months->sum('collected'),
            'priorCollected' => $prior->where('month', '<=', $upTo)->sum('collected'),
            'consultants' => $entered->sum('consultants'),
            'backlog' => $remaining->sum(fn (array $row) => (float) $row['balance']),
            'backlogDesign' => $remaining->sum(fn (array $row) => (float) $row['balanceDesign']),
            'backlogCa' => $remaining->sum(fn (array $row) => (float) $row['balanceCA']),
            'backlogJobs' => $remaining->count(),
            'divisions' => $divisions,
            'chart' => [
                'labels' => $months->pluck('label')->all(),
                'actual' => $months->pluck('billed')->all(),
                'runRate' => $months->map(fn (array $month) => $projectedMonths->contains('month', $month['month']) ? round($runRate, 2) : null)->all(),
                'prior' => $prior->pluck('billed')->all(),
            ],
            'ring' => [
                'labels' => array_keys($divisions),
                'values' => array_values($divisions),
                'colors' => self::Slots,
            ],
        ];
    }

    public function render(): View
    {
        return view('livewire.overview', ['slots' => self::Slots]);
    }
}

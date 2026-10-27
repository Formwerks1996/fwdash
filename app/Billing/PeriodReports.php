<?php

namespace App\Billing;

use App\Support\Money;
use App\Support\RateBand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/*
 * The figures behind Month End, its printed reports and Extras, composed
 * from the billing engine so the screen and the paper can never disagree.
 */
class PeriodReports
{
    /** Revenue categories, matched on project division. "Other" catches the rest, blanks included. */
    public const Divisions = [
        'Multi-family' => ['MF'],
        'Single family' => ['SF'],
        'Interior' => ['INT', 'INT-MF'],
        'Multiplex / CHC' => ['MPX', 'CHC'],
        'Renovation' => ['REN'],
        'Other' => [],
    ];

    /** A category keeps its colour whatever its rank. */
    public const Slots = ['#00263E', '#b5654a', '#6f8f72', '#85754E', '#6d5a7b', '#3d5a40', '#5c6873', '#4f7392'];

    /** Report filters and the label each prints under. */
    public const Filters = [
        'billing' => ['label' => 'Billed this month', 'print' => 'Jobs billed this month'],
        'active' => ['label' => 'All active jobs', 'print' => 'All active jobs'],
        'owing' => ['label' => 'Anything outstanding', 'print' => 'Jobs with anything outstanding'],
        'all' => ['label' => 'Every job', 'print' => 'Every job'],
    ];

    /** Age of the last payment, measured back from the period end. */
    public const AgeBands = [
        ['key' => 'a0', 'label' => 'Under 30 days', 'color' => '#2f8a67'],
        ['key' => 'a1', 'label' => '30–60', 'color' => '#9cc78a'],
        ['key' => 'a2', 'label' => '60–90', 'color' => '#e3b341'],
        ['key' => 'a3', 'label' => 'Over 90 days', 'color' => '#c4694a'],
        ['key' => 'a4', 'label' => 'No payment yet', 'color' => '#b6bbc1'],
    ];

    /** @var array<int, array{months: array<int, array<string, mixed>>, lastEntered: ?string, runRate: float}> */
    protected array $seriesCache = [];

    public function __construct(protected BillingEngine $engine) {}

    /** Years with more than one billing period; 2020 is a single opening-balance lump. */
    /** @return array<int, int> */
    public function years(): array
    {
        return collect($this->engine->periods())
            ->countBy(fn (string $period) => (int) substr($period, 0, 4))
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * Twelve months of billing. A month nobody has run month end on is shown at
     * the year's run rate (the mean of the months entered), and only in the
     * current year after the last month entered; past gaps stay empty.
     *
     * @return array{months: array<int, array<string, mixed>>, lastEntered: ?string, runRate: float}
     */
    public function yearSeries(int $year): array
    {
        if (isset($this->seriesCache[$year])) {
            return $this->seriesCache[$year];
        }

        $periods = collect($this->engine->periods())->keyBy(fn (string $period) => substr($period, 0, 7));
        $collected = $this->engine->paymentsByMonth();
        $hours = collect($this->engine->hoursByPeriod())->mapWithKeys(fn (float $value, string $period) => [substr($period, 0, 7) => $value]);
        $today = CarbonImmutable::today();

        $months = collect(range(1, 12))->map(function (int $month) use ($year, $periods, $collected, $hours, $today) {
            $start = CarbonImmutable::create($year, $month, 1);
            $key = $start->format('Y-m');
            $period = $periods[$key] ?? null;
            $totals = $period ? $this->engine->periodTotals($period) : null;

            return [
                'key' => $key,
                'month' => $month,
                'label' => $start->format('M'),
                'name' => $start->format('F'),
                'period' => $period,
                'billed' => $totals ? (float) $totals['billed'] : null,
                'phaseBilled' => $totals ? (float) $totals['phaseBilled'] : null,
                'hourlyBilled' => $totals ? (float) $totals['hourlyBilled'] : null,
                'jobCount' => $totals['jobCount'] ?? 0,
                'collected' => isset($collected[$key]) ? (float) $collected[$key] : null,
                'hours' => $hours[$key] ?? null,
                'complete' => $start->endOfMonth()->lt($today),
                'current' => $key === $today->format('Y-m'),
            ];
        });

        $entered = $months->whereNotNull('billed');
        $lastEntered = $entered->last();
        $runRate = $entered->isNotEmpty() ? $entered->sum('billed') / $entered->count() : 0.0;
        $projects = $year === (int) $today->format('Y');

        $months = $months->map(function (array $month) use ($lastEntered, $runRate, $projects) {
            if ($month['billed'] !== null) {
                return [...$month, 'basis' => 'actual', 'revenue' => $month['billed']];
            }

            if ($projects && $runRate && $month['month'] > ($lastEntered['month'] ?? 0)) {
                return [...$month, 'basis' => 'runrate', 'revenue' => round($runRate, 2)];
            }

            return [...$month, 'basis' => null, 'revenue' => null];
        });

        return $this->seriesCache[$year] = [
            'months' => $months->values()->all(),
            'lastEntered' => $lastEntered['period'] ?? null,
            'runRate' => $runRate,
        ];
    }

    /** The billing period before this one, as billing_periods has them (not necessarily the previous calendar month). */
    public function previousPeriod(string $period): ?string
    {
        $earlier = array_filter($this->engine->periods(), fn (string $candidate) => $candidate < $period);

        return $earlier ? max($earlier) : null;
    }

    public static function divisionOf(?string $code): string
    {
        foreach (self::Divisions as $label => $codes) {
            if (in_array($code, $codes, true)) {
                return $label;
            }
        }

        return 'Other';
    }

    /**
     * One row per job, as at the period: the partners' report.
     *
     * @param  array{jobs: array<int, array<string, mixed>>}  $report
     * @return Collection<int, array<string, mixed>>
     */
    public function monthEndRows(string $period, array $report): Collection
    {
        $billedThisMonth = [];

        foreach ($report['jobs'] as $block) {
            $billedThisMonth[$block['jobNumber']] = (float) $block['totals']['billed'];
        }

        return collect($this->engine->allSummaries($period))
            ->map(fn (array $summary) => $this->monthEndRow($summary, $period, $billedThisMonth))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, float>  $billedThisMonth
     * @return array<string, mixed>
     */
    protected function monthEndRow(array $summary, string $period, array $billedThisMonth): array
    {
        $hoursMonth = $this->engine->hoursIn($summary['jobNumber'], $period);

        return [
            'jobNumber' => $summary['jobNumber'],
            'jobName' => $summary['jobName'],
            'developer' => $summary['developer'],
            'division' => $summary['division'],
            'active' => $summary['active'],
            'onReport' => array_key_exists($summary['jobNumber'], $billedThisMonth),
            'billedMonth' => $billedThisMonth[$summary['jobNumber']] ?? 0.0,
            'hoursMonth' => $hoursMonth ?: null,
            'hoursLive' => $summary['hoursTotal'],
            'rateLive' => $summary['ratePerHour'],
            'contract' => (float) $summary['contract'],
            'billed' => (float) $summary['billed'],
            'hourlyBilled' => (float) $summary['hourlyBilled'],
            'billedPct' => $summary['billedPct'],
            'balance' => (float) $summary['balance'],
            'balanceDesign' => (float) $summary['balanceDesign'],
            'balanceCA' => (float) $summary['balanceCA'],
            'consultantTotal' => (float) $summary['consultantTotal'],
            'hoursTotal' => $summary['hoursTotal'],
            'paymentsTotal' => (float) $summary['paymentsTotal'],
            'lastPaymentDate' => $summary['lastPaymentDate'],
            'lastPaymentAmount' => $summary['lastPaymentAmount'] === null ? null : (float) $summary['lastPaymentAmount'],
            'outstanding' => (float) $summary['outstanding'],
            'daysSincePayment' => $summary['lastPaymentDate']
                ? (int) round(CarbonImmutable::parse($summary['lastPaymentDate'])->diffInDays(CarbonImmutable::parse($period)))
                : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function filterRows(Collection $rows, string $only): Collection
    {
        return $rows
            ->filter(fn (array $row) => match ($only) {
                'active' => $row['active'],
                'owing' => $row['outstanding'] > 0,
                'all' => true,
                default => $row['onReport'] || $row['billedMonth'],
            })
            ->values();
    }

    /**
     * Blanks always sort last, whichever way the column runs.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function sortRows(Collection $rows, string $column, string $direction): Collection
    {
        $descending = $direction === 'desc';

        [$blank, $filled] = $rows->partition(fn (array $row) => ($row[$column] ?? null) === null || $row[$column] === '');

        $sorted = in_array($column, ['jobName', 'developer', 'jobNumber', 'division'], true)
            ? $filled->sortBy(fn (array $row) => mb_strtolower((string) $row[$column]), SORT_NATURAL, $descending)
            : $filled->sortBy($column, SORT_REGULAR, $descending);

        return $sorted->concat($blank)->values();
    }

    /**
     * Everything Month End shows for a period, apart from the job table's order.
     *
     * @return array<string, mixed>
     */
    public function monthEnd(string $period, string $only): array
    {
        $report = $this->engine->periodReport($period);
        $rows = $this->filterRows($this->monthEndRows($period, $report), $only);
        $year = (int) substr($period, 0, 4);
        $series = $this->yearSeries($year);
        $prior = $this->yearSeries($year - 1);

        $previous = $this->previousPeriod($period);
        $previousTotal = $previous ? (float) $this->engine->periodTotals($previous)['billed'] : null;
        $total = (float) $report['totalThisMonth'];

        $ytd = collect($series['months'])
            ->filter(fn (array $month) => $month['key'] <= substr($period, 0, 7) && $month['basis'] === 'actual')
            ->sum('billed');

        $hoursListed = (float) $rows->sum('hoursMonth');
        $realized = $hoursListed ? $total / $hoursListed : null;

        $under = $rows->filter(fn (array $row) => $row['rateLive'] !== null && $row['rateLive'] < RateBand::Bands['low']['max']);

        $selected = (int) substr($period, 5, 2) - 1;

        return [
            'report' => $report,
            'rows' => $rows,
            'year' => $year,
            'total' => $total,
            'previousTotal' => $previousTotal,
            'change' => $previousTotal ? ($total - $previousTotal) / $previousTotal : null,
            'ytd' => $ytd,
            'hoursListed' => $hoursListed,
            'realized' => $realized,
            'under' => [
                'count' => $under->count(),
                'names' => $under->pluck('jobName')->take(2)->implode(', ').($under->count() > 2 ? ' +'.($under->count() - 2) : ''),
            ],
            'series' => $series,
            'chart' => [
                'labels' => array_column($series['months'], 'label'),
                'revenue' => array_column($series['months'], 'revenue'),
                'bases' => array_column($series['months'], 'basis'),
                'prior' => array_column($prior['months'], 'billed'),
                'selected' => $selected,
                'clickable' => array_map(fn (array $month) => $month['period'] !== null, $series['months']),
            ],
            'estimated' => collect($series['months'])->contains(fn (array $month) => $month['basis'] === 'runrate'),
            'rings' => $this->rings($period, $report),
            'outstanding' => $this->outstanding($rows),
        ];
    }

    /**
     * Billed and hours, by division, for one month.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, array{title: string, note: string, money: bool, total: float, slices: array<int, array{label: string, value: float, color: string}>}>
     */
    public function rings(string $period, array $report): array
    {
        $billed = array_fill_keys(array_keys(self::Divisions), 0.0);
        $hours = $billed;

        foreach ($report['jobs'] as $block) {
            $billed[self::divisionOf($block['division'])] += (float) $block['totals']['billed'];
        }

        foreach ($this->engine->jobs() as $jobNumber => $job) {
            $hours[self::divisionOf($job['division'])] += $this->engine->hoursIn((string) $jobNumber, $period);
        }

        $slices = fn (array $values) => array_map(
            fn (string $label, float $value, int $index) => ['label' => $label, 'value' => $value, 'color' => self::Slots[$index]],
            array_keys($values),
            array_values($values),
            array_keys(array_keys($values)),
        );

        return [
            'billed' => ['title' => __('Billed'), 'note' => __('Invoiced this month, by division.'), 'money' => true, 'total' => array_sum($billed), 'slices' => $slices($billed)],
            'hours' => ['title' => __('Hours'), 'note' => __('Hours logged this month, by division.'), 'money' => false, 'total' => array_sum($hours), 'slices' => $slices($hours)],
        ];
    }

    /**
     * What is still owed on the listed jobs, aged by the last payment and grouped by client.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{total: float, jobs: int, clients: array<int, array<string, mixed>>, bands: array<int, array<string, mixed>>}
     */
    public function outstanding(Collection $rows): array
    {
        $owing = $rows->filter(fn (array $row) => $row['outstanding'] > 0);
        $total = (float) $owing->sum('outstanding');

        $bands = collect(self::AgeBands)
            ->map(fn (array $band) => [...$band, 'amount' => (float) $owing->filter(fn (array $row) => self::ageBand($row['daysSincePayment']) === $band['key'])->sum('outstanding')])
            ->filter(fn (array $band) => $band['amount'] > 0)
            ->map(fn (array $band) => [...$band, 'share' => $total ? $band['amount'] / $total : 0])
            ->values()
            ->all();

        $clients = $owing
            ->groupBy(fn (array $row) => trim((string) $row['developer']) ?: 'Unknown')
            ->map(fn (Collection $jobs, string $client) => [
                'client' => $client,
                'owed' => (float) $jobs->sum('outstanding'),
                'jobs' => $jobs->count(),
                'last' => $jobs->pluck('lastPaymentDate')->filter()->max(),
                'late' => $jobs->pluck('lastPaymentDate')->filter()->isEmpty()
                    || $jobs->min(fn (array $row) => $row['daysSincePayment'] ?? PHP_INT_MAX) >= 90,
            ])
            ->sortByDesc('owed')
            ->values()
            ->all();

        return ['total' => $total, 'jobs' => $owing->count(), 'clients' => $clients, 'bands' => $bands];
    }

    public static function ageBand(?int $days): string
    {
        return match (true) {
            $days === null => 'a4',
            $days < 30 => 'a0',
            $days < 60 => 'a1',
            $days < 90 => 'a2',
            default => 'a3',
        };
    }

    /** The colour class for an outstanding figure, by how long since the last payment. */
    public static function ageClass(?int $days): string
    {
        return match (true) {
            $days === null => 'text-muted',
            $days > 90 => 'font-medium text-[#b8492c]',
            $days > 60 => 'text-[#9d6714]',
            default => '',
        };
    }
}

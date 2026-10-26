<?php

namespace App\Livewire;

use App\Billing\BillingEngine;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Hourly billing month by month: the workbook's "Extras", time and materials
 * (not change orders).
 *
 * The old page took its hours from Mosaic time logged on "hourly" phases and
 * priced them at cost. Without Mosaic the hours are the staff lines typed at
 * month end, the dollars are what was invoiced as hourly, and nothing claims to
 * be profit. Read-only.
 */
/**
 * @property-read array{months: array<int, array<string, mixed>>, jobMonths: array<int, array<string, mixed>>, personMonths: array<int, array<string, mixed>>, lines: array<int, array<string, mixed>>, jobs: array<string, array<string, mixed>>} $extras
 * @property-read Collection<int, array<string, mixed>> $months
 * @property-read Collection<int, array<string, mixed>> $window
 * @property-read array<string, mixed>|null $month
 * @property-read Collection<int, array<string, mixed>> $jobRows
 * @property-read Collection<int, array<string, mixed>> $people
 * @property-read array<string, mixed> $matrix
 * @property-read array<string, array<int, array<string, mixed>>> $linesByJobMonth
 */
#[Title('Extras')]
class Extras extends Component
{
    public const MatrixTop = 20;

    #[Url]
    public ?string $period = null;

    #[Url]
    public string $range = '12';

    public string $scope = 'month';

    public string $group = 'job';

    public string $metric = 'hours';

    public bool $matrixAll = false;

    /** @var array<int, string> */
    public array $open = [];

    public function mount(): void
    {
        if (! $this->period || ! $this->months->contains('period', $this->period)) {
            $this->period = $this->latestBilled($this->months);
        }

        if (! $this->isRange($this->range)) {
            $this->range = '12';
        }

        if ($this->period && ! $this->window->contains('period', $this->period)) {
            $this->range = substr($this->period, 0, 4);
            unset($this->window);
        }
    }

    /** @return array{months: array<int, array<string, mixed>>, jobMonths: array<int, array<string, mixed>>, personMonths: array<int, array<string, mixed>>, lines: array<int, array<string, mixed>>, jobs: array<string, array<string, mixed>>} */
    #[Computed]
    public function extras(): array
    {
        return app(BillingEngine::class)->extras();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function months(): Collection
    {
        return collect($this->extras['months']);
    }

    /**
     * "Last N" ends at the newest month, not the selected one, so clicking a bar
     * never slides the chart out from under the pointer.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function window(): Collection
    {
        return match (true) {
            $this->range === 'all' => $this->months,
            (bool) preg_match('/^\d{4}$/', $this->range) => $this->months->filter(fn (array $month) => str_starts_with($month['period'], $this->range))->values(),
            default => $this->months->slice(-(int) $this->range)->values(),
        };
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function month(): ?array
    {
        return $this->months->firstWhere('period', $this->period);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    #[Computed]
    public function linesByJobMonth(): array
    {
        return collect($this->extras['lines'])
            ->groupBy(fn (array $line) => "{$line['period']}|{$line['jobNumber']}")
            ->map(fn (Collection $lines) => $lines->values()->all())
            ->all();
    }

    /**
     * The selected month job by job, or its whole year added up per job.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function jobRows(): Collection
    {
        $jobs = $this->extras['jobs'];
        $describe = fn (string $jobNumber) => [
            'jobNumber' => $jobNumber,
            'jobName' => $jobs[$jobNumber]['jobName'] ?? $jobNumber,
            'developer' => $jobs[$jobNumber]['developer'] ?? null,
        ];

        if ($this->scope === 'year') {
            $year = substr((string) $this->period, 0, 4);

            return $this->asRows(collect($this->extras['jobMonths'])
                ->filter(fn (array $cell) => str_starts_with($cell['period'], $year))
                ->groupBy('jobNumber')
                ->map(function (Collection $cells, string $jobNumber) use ($describe): array {
                    $billedHours = $cells->filter(fn (array $cell) => (float) $cell['billed'] != 0)->sum('hours');
                    $billed = $cells->sum(fn (array $cell) => (float) $cell['billed']);

                    return [
                        ...$describe($jobNumber),
                        'people' => $cells->pluck('people')->flatten()->unique()->sort()->values()->all(),
                        'hours' => round((float) $cells->sum('hours'), 2),
                        'billed' => $billed,
                        'offLines' => $billed - $cells->sum(fn (array $cell) => (float) $cell['linesTotal']),
                        'rate' => $billedHours ? $billed / $billedHours : null,
                        'kind' => null,
                        'months' => $cells->sortByDesc('period')->values()->all(),
                    ];
                })
                ->filter(fn (array $row) => $row['hours'] || $row['billed'])
                ->sortBy([['hours', 'desc'], ['billed', 'desc']]));
        }

        return $this->asRows(collect($this->extras['jobMonths'])
            ->filter(fn (array $cell) => $cell['period'] === $this->period && ($cell['hours'] || (float) $cell['billed'] != 0 || $cell['lineCount']))
            ->map(fn (array $cell): array => [
                ...$describe($cell['jobNumber']),
                'people' => $cell['people'],
                'hours' => $cell['hours'],
                'billed' => (float) $cell['billed'],
                'linesTotal' => (float) $cell['linesTotal'],
                'offLines' => (float) $cell['billed'] - (float) $cell['linesTotal'],
                'rate' => $cell['rate'],
                'kind' => $cell['kind'],
                'override' => $cell['override'],
                'lines' => $this->linesByJobMonth["{$cell['period']}|{$cell['jobNumber']}"] ?? [],
            ])
            ->sortBy([['hours', 'desc'], ['billed', 'desc']]));
    }

    /**
     * Who logged the time, in the selected month or its year.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function people(): Collection
    {
        $year = substr((string) $this->period, 0, 4);
        $jobs = $this->extras['jobs'];

        return $this->asRows(collect($this->extras['personMonths'])
            ->filter(fn (array $person) => $this->scope === 'year' ? str_starts_with($person['period'], $year) : $person['period'] === $this->period)
            ->groupBy('staff')
            ->map(fn (Collection $rows, string $staff): array => [
                'staff' => $staff,
                'hours' => round((float) $rows->sum('hours'), 2),
                'jobs' => $rows->pluck('jobs')->flatten()->unique()->map(fn (string $jobNumber) => $jobs[$jobNumber]['jobName'] ?? $jobNumber)->sort()->values()->all(),
            ])
            ->filter(fn (array $person) => $person['hours'] > 0)
            ->sortByDesc('hours'));
    }

    /**
     * Where the extras came from: rows by job or person, columns by month (or by
     * year once the window is over 24 months).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function matrix(): array
    {
        $byYear = $this->window->count() > 24;
        $columns = $this->window
            ->map(fn (array $month) => $byYear ? substr($month['period'], 0, 4) : $month['period'])
            ->unique()
            ->values()
            ->all();

        $dollars = $this->group === 'job' && $this->metric === 'billed';
        $periods = $this->window->pluck('period')->flip();
        $jobs = $this->extras['jobs'];
        $cells = [];

        $source = $this->group === 'job' ? $this->extras['jobMonths'] : $this->extras['personMonths'];

        foreach ($source as $item) {
            if (! $periods->has($item['period'])) {
                continue;
            }

            $row = $this->group === 'job' ? $item['jobNumber'] : $item['staff'];
            $column = $byYear ? substr($item['period'], 0, 4) : $item['period'];
            $value = $dollars ? (float) $item['billed'] : (float) $item['hours'];

            $cells[$row][$column] = ($cells[$row][$column] ?? 0.0) + $value;
        }

        $rows = collect($cells)
            ->map(fn (array $values, string|int $key) => [
                'key' => (string) $key,
                'label' => $this->group === 'job' ? ($jobs[(string) $key]['jobName'] ?? (string) $key) : (string) $key,
                'jobNumber' => $this->group === 'job' ? (string) $key : null,
                'cells' => $values,
                'total' => array_sum($values),
            ])
            ->filter(fn (array $row) => collect($row['cells'])->contains(fn (float $value) => abs($value) >= 0.005))
            ->sortByDesc('total')
            ->values();

        $max = $rows->flatMap(fn (array $row) => array_values($row['cells']))->max() ?? 0.0;

        return [
            'byYear' => $byYear,
            'dollars' => $dollars,
            'columns' => $columns,
            'rows' => $this->matrixAll ? $rows : $rows->take(self::MatrixTop),
            'count' => $rows->count(),
            'totals' => collect($columns)->mapWithKeys(fn (string $column) => [$column => $rows->sum(fn (array $row) => $row['cells'][$column] ?? 0.0)])->all(),
            'total' => $rows->sum('total'),
            'max' => max(0.0, (float) $max),
        ];
    }

    public function updatedRange(): void
    {
        if (! $this->isRange($this->range)) {
            $this->range = '12';
        }

        $this->matrixAll = false;
        unset($this->window, $this->matrix);

        if (! $this->window->contains('period', $this->period)) {
            $this->period = $this->latestBilled($this->window);
        }
    }

    public function updatedPeriod(): void
    {
        $this->selectMonth((string) $this->period);
    }

    public function selectMonth(string $period): void
    {
        if (! $this->months->contains('period', $period)) {
            return;
        }

        $this->period = $period;
        $this->open = [];

        if (! $this->window->contains('period', $period)) {
            $this->range = substr($period, 0, 4);
            unset($this->window, $this->matrix);
        }

        unset($this->month, $this->jobRows, $this->people);
    }

    public function selectIndex(int $index): void
    {
        $month = $this->window->get($index);

        if ($month) {
            $this->selectMonth($month['period']);
        }
    }

    public function openMonth(string $period): void
    {
        $this->scope = 'month';
        $this->selectMonth($period);
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope === 'year' ? 'year' : 'month';
        $this->open = [];
    }

    public function setGroup(string $group): void
    {
        $this->group = $group === 'person' ? 'person' : 'job';
        $this->matrixAll = false;
    }

    public function setMetric(string $metric): void
    {
        $this->metric = $metric === 'billed' ? 'billed' : 'hours';
    }

    public function toggle(string $jobNumber): void
    {
        $this->open = in_array($jobNumber, $this->open, true)
            ? array_values(array_diff($this->open, [$jobNumber]))
            : [...$this->open, $jobNumber];
    }

    public function toggleAll(): void
    {
        $all = $this->jobRows->pluck('jobNumber')->all();

        $this->open = count($this->open) >= count($all) ? [] : $all;
    }

    /** $5.4k, $12k, $1.20M. */
    public static function compact(mixed $value): string
    {
        if ($value === null || $value === '') {
            return Format::Empty;
        }

        $number = (float) $value;
        $absolute = abs($number);
        $sign = $number < 0 ? '−' : '';

        return match (true) {
            $absolute >= 1_000_000 => $sign.'$'.number_format($absolute / 1_000_000, 2).'M',
            $absolute >= 1_000 => $sign.'$'.number_format($absolute / 1_000, $absolute >= 10_000 ? 0 : 1).'k',
            default => $sign.'$'.number_format(round($absolute)),
        };
    }

    public static function hours(mixed $value): string
    {
        if ($value === null || $value === '') {
            return Format::Empty;
        }

        $number = (float) $value;

        return Format::number($number, abs($number) < 10 && fmod($number, 1.0) != 0.0 ? 2 : 0);
    }

    /**
     * Invoiced over hours, both summed first and only over months that billed:
     * never an average of monthly rates.
     *
     * @param  Collection<int, array<string, mixed>>  $months
     */
    public static function rateOf(Collection $months): ?float
    {
        $billed = $months->filter(fn (array $month) => (float) $month['billed'] != 0);
        $hours = (float) $billed->sum('hours');

        return $hours ? $billed->sum(fn (array $month) => (float) $month['billed']) / $hours : null;
    }

    /** @return array<int, array{key: string, label: string}> */
    public function ranges(): array
    {
        $years = $this->months
            ->map(fn (array $month) => substr($month['period'], 0, 4))
            ->unique()
            ->sortDesc()
            ->map(fn (string $year) => ['key' => $year, 'label' => $year])
            ->values()
            ->all();

        return [
            ['key' => '12', 'label' => __('Last 12 months')],
            ['key' => '24', 'label' => __('Last 24 months')],
            ...$years,
            ['key' => 'all', 'label' => __('Every month')],
        ];
    }

    public function render(): View
    {
        $window = $this->window;
        $month = $this->month;

        $previous = $month
            ? $this->months->filter(fn (array $row) => $row['period'] < $month['period'] && $row['hours'])->last()
            : null;

        $after = $window->map(fn (array $row) => $row['jobs'] || $row['people']
            ? trans_choice(':count job|:count jobs', $row['jobs']).' · '.trans_choice(':count person|:count people', $row['people'])
            : __('no extras'))->all();

        $chart = fn (string $key, string $metric) => [
            'labels' => $window->map(fn (array $row) => CarbonImmutable::parse($row['period'])->format('M y'))->all(),
            'titles' => $window->map(fn (array $row) => Format::month($row['period']))->all(),
            'values' => $window->map(fn (array $row) => (float) $row[$key])->all(),
            'selected' => $window->search(fn (array $row) => $row['period'] === $this->period),
            'metric' => $metric,
            'after' => $after,
        ];

        return view('livewire.extras', [
            'previous' => $previous,
            'rangeLabel' => collect($this->ranges())->firstWhere('key', $this->range)['label'] ?? $this->range,
            'hoursChart' => $chart('hours', 'hours'),
            'billedChart' => $chart('billed', 'money'),
        ]);
    }

    /** @param Collection<int, array<string, mixed>> $months */
    protected function latestBilled(Collection $months): ?string
    {
        $billed = $months->filter(fn (array $month) => (float) $month['billed'] != 0 || $month['hours'])->last();

        return $billed['period'] ?? $months->last()['period'] ?? null;
    }

    /**
     * @param  iterable<array-key, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function asRows(iterable $rows): Collection
    {
        return collect($rows)->values();
    }

    protected function isRange(string $range): bool
    {
        return in_array($range, ['12', '24', 'all'], true) || preg_match('/^\d{4}$/', $range) === 1;
    }
}

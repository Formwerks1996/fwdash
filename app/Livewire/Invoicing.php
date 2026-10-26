<?php

namespace App\Livewire;

use App\Billing\BillingEngine;
use App\Livewire\Concerns\SortsRows;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, array<string, mixed>> $ongoing
 * @property-read Collection<int, array<string, mixed>> $rows
 * @property-read array<string, mixed> $totals
 * @property-read array{labels: array<int, string>, payments: array<int, float>, hours: array<int, float>} $activity
 */
#[Title('Invoicing')]
class Invoicing extends Component
{
    use SortsRows;

    #[Url]
    public ?string $period = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $division = '';

    #[Url]
    public string $flag = 'all';

    public string $sort = 'billedThisMonth';

    public string $direction = 'desc';

    /** @var array<int, string> */
    protected array $textColumns = ['jobNumber', 'jobName', 'division'];

    public function mount(BillingEngine $engine): void
    {
        $this->period ??= $engine->latestPeriod();
    }

    /**
     * Ongoing projects: active with fee still to bill.
     *
     * @return Collection<int, non-empty-array<string, mixed>>
     */
    #[Computed]
    public function ongoing(): Collection
    {
        $engine = app(BillingEngine::class);

        return collect($engine->allSummaries())
            ->filter(fn (array $row) => $row['active'] && Money::compare($row['balance'], 0) > 0)
            ->map(function (array $row) use ($engine) {
                $month = $this->period
                    ? $engine->jobPeriodTotals($row['jobNumber'], $this->period)
                    : ['billed' => '0', 'hourlyBilled' => '0'];

                return [
                    ...$row,
                    'billedThisMonth' => (float) $month['billed'],
                    'extrasThisMonth' => (float) $month['hourlyBilled'],
                    'hoursThisMonth' => $this->period ? $engine->hoursIn($row['jobNumber'], $this->period) : 0,
                    'contract' => (float) $row['contract'],
                    'billed' => (float) $row['billed'],
                    'balance' => (float) $row['balance'],
                    'balanceDesign' => (float) $row['balanceDesign'],
                    'balanceCA' => (float) $row['balanceCA'],
                    'outstanding' => (float) $row['outstanding'],
                    'hours' => $row['hoursTotal'],
                ];
            })
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        $search = Str::lower(trim($this->search));

        $rows = $this->ongoing
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $row) => Str::contains(Str::lower(implode(' ', [$row['developer'], $row['jobName'], $row['jobNumber'], $row['lead']])), $search),
            ))
            ->when($this->division !== '', fn (Collection $rows) => $rows->where('division', $this->division))
            ->filter(fn (array $row) => match ($this->flag) {
                'billed' => $row['billedThisMonth'] != 0,
                'over' => $row['contract'] && $row['billed'] > $row['contract'],
                'owing' => $row['outstanding'] > 0,
                default => true,
            });

        return $this->sortRows($rows);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function totals(): array
    {
        $rows = $this->ongoing;
        $sum = fn (string $key) => $rows->sum($key);

        return [
            'billedThisMonth' => $sum('billedThisMonth'),
            'extrasThisMonth' => $sum('extrasThisMonth'),
            'contract' => $sum('contract'),
            'billed' => $sum('billed'),
            'balance' => $sum('balance'),
            'balanceDesign' => $sum('balanceDesign'),
            'balanceCA' => $sum('balanceCA'),
            'outstanding' => $sum('outstanding'),
            'hours' => $sum('hours'),
            'hoursThisMonth' => $sum('hoursThisMonth'),
            'owingCount' => $rows->where('outstanding', '>', 0)->count(),
            'divisions' => $rows->pluck('division')->filter()->unique()->count(),
            'overContract' => $rows->filter(fn (array $row) => $row['contract'] && $row['billed'] > $row['contract'])->count(),
            'nothingThisMonth' => $rows->where('billedThisMonth', 0)->count(),
        ];
    }

    /** @return array{labels: array<int, string>, payments: array<int, float>, hours: array<int, float>} */
    #[Computed]
    public function activity(): array
    {
        $engine = app(BillingEngine::class);
        $end = CarbonImmutable::parse($this->period ?? now());
        $months = collect(range(17, 0))->map(fn (int $back) => $end->startOfMonth()->subMonths($back));

        $payments = $engine->paymentsByMonth();
        $hours = collect($engine->hoursByPeriod())->mapWithKeys(fn (float $value, string $period) => [substr($period, 0, 7) => $value]);

        return [
            'labels' => $months->map(fn (CarbonImmutable $month) => $month->format('M y'))->all(),
            'payments' => $months->map(fn (CarbonImmutable $month) => (float) ($payments[$month->format('Y-m')] ?? 0))->all(),
            'hours' => $months->map(fn (CarbonImmutable $month) => (float) ($hours[$month->format('Y-m')] ?? 0))->all(),
        ];
    }

    public function render(BillingEngine $engine): View
    {
        return view('livewire.invoicing', [
            'periods' => array_reverse($engine->periods()),
            'engine' => $engine,
            'allDivisions' => $this->ongoing->pluck('division')->filter()->unique()->sort()->values(),
            'completedCount' => count($engine->jobs()) - $this->ongoing->count(),
        ]);
    }
}

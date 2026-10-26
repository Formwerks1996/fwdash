<?php

namespace App\Livewire;

use App\Actions\Billing\SetMonthlyTargetAction;
use App\Billing\BillingEngine;
use App\Billing\PeriodReports;
use App\Enums\Permission;
use App\Models\MonthlyTarget;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * A revenue target per month, set against what was invoiced. A month with no
 * billing entered shows the year's run rate, greyed and labelled, so an
 * unfinished month end never reads as a miss.
 */
/**
 * @property-read array<int, int> $years
 * @property-read Collection<string, MonthlyTarget> $targets
 * @property-read Collection<int, non-empty-array<string, mixed>> $months
 * @property-read array<string, mixed> $figures
 */
#[Title('Monthly Targets')]
class Targets extends Component
{
    #[Url]
    public ?int $year = null;

    public string $fillAmount = '';

    public function mount(): void
    {
        $this->year ??= (int) now()->format('Y');
    }

    /** @return array<int, int> */
    #[Computed]
    public function years(): array
    {
        return collect($this->reports()->years())
            ->push((int) now()->format('Y') + 1, (int) $this->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /** @return Collection<string, MonthlyTarget> */
    #[Computed]
    public function targets(): Collection
    {
        return MonthlyTarget::where('month', 'like', "{$this->year}-%")
            ->orderBy('month')
            ->get()
            ->keyBy('month');
    }

    /** @return Collection<int, non-empty-array<string, mixed>> */
    #[Computed]
    public function months(): Collection
    {
        $series = $this->reports()->yearSeries((int) $this->year);

        return collect($series['months'])->map(function (array $month) {
            $target = $this->targets->get($month['key']);
            $actual = $month['billed'] !== null;
            $shown = $actual ? $month['billed'] : $month['revenue'];

            return [
                ...$month,
                'target' => $target ? (float) $target->amount : null,
                'note' => $target?->note,
                'actual' => $actual,
                'shown' => $shown,
                'estimate' => ! $actual && $shown !== null,
                'pace' => ! $actual && $month['basis'] === 'runrate',
            ];
        });
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function figures(): array
    {
        $months = $this->months;
        $withTarget = $months->whereNotNull('target');
        $done = $withTarget->where('actual', true);
        $billed = (float) $done->sum('billed');
        $target = (float) $done->sum('target');
        $yearTarget = (float) $withTarget->sum('target');
        $projected = (float) $months->sum('revenue');

        return [
            'yearTarget' => $withTarget->isNotEmpty() ? $yearTarget : null,
            'targetCount' => $withTarget->count(),
            'doneCount' => $done->count(),
            'doneBilled' => $billed,
            'doneTarget' => $target,
            'hits' => $done->filter(fn (array $month) => $month['billed'] >= $month['target'])->count(),
            'ytd' => (float) $months->where('actual', true)->sum('billed'),
            'projected' => $projected,
            'projectedGap' => $withTarget->count() === 12 ? $projected - $yearTarget : null,
            'collected' => (float) $months->sum('collected'),
            'lastEntered' => $this->reports()->yearSeries((int) $this->year)['lastEntered'],
            'existingCount' => $withTarget->count(),
            'chart' => [
                'labels' => $months->pluck('label')->all(),
                'titles' => $months->map(fn (array $month) => "{$month['name']} {$this->year}")->all(),
                'shown' => $months->pluck('shown')->all(),
                'estimate' => $months->pluck('estimate')->all(),
                'hit' => $months->map(fn (array $month) => $month['actual'] && $month['target'] !== null && $month['billed'] >= $month['target'])->all(),
                'target' => $months->pluck('target')->all(),
            ],
        ];
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
        $this->resetErrorBag();
    }

    public function saveTarget(string $month, ?string $value, SetMonthlyTargetAction $setTarget): void
    {
        $this->authorize(Permission::EditBilling->value);
        $this->resetErrorBag();

        $amount = SetMonthlyTargetAction::parseAmount($value);
        $current = $this->targets->get($month);

        if ($amount === ($current ? (string) round((float) $current->amount) : null)) {
            return;
        }

        $setTarget->execute($month, $amount);

        $this->refreshFigures();
    }

    public function saveNote(string $month, ?string $note, SetMonthlyTargetAction $setTarget): void
    {
        $this->authorize(Permission::EditBilling->value);
        $this->resetErrorBag();

        if ((trim((string) $note) ?: null) === $this->targets->get($month)?->note) {
            return;
        }

        $setTarget->saveNote($month, $note);

        $this->refreshFigures();
    }

    public function fillMonths(bool $all, SetMonthlyTargetAction $setTarget): void
    {
        $this->authorize(Permission::EditBilling->value);
        $this->resetErrorBag();

        $months = $this->months
            ->filter(fn (array $month) => $all || $month['target'] === null)
            ->pluck('key')
            ->all();

        $setTarget->fill($months, SetMonthlyTargetAction::parseAmount($this->fillAmount));

        $this->fillAmount = '';
        $this->refreshFigures();
    }

    public function render(): View
    {
        return view('livewire.targets', [
            'canEdit' => auth()->user()->can(Permission::EditBilling->value),
        ]);
    }

    protected function refreshFigures(): void
    {
        unset($this->targets, $this->months, $this->figures);
    }

    protected function reports(): PeriodReports
    {
        return new PeriodReports(app(BillingEngine::class));
    }
}

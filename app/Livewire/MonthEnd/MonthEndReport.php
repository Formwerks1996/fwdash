<?php

namespace App\Livewire\MonthEnd;

use App\Actions\Billing\SetPeriodStatusAction;
use App\Billing\BillingEngine;
use App\Billing\PeriodReports;
use App\Enums\Permission;
use App\Livewire\Concerns\SortsRows;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Where the month stands, for the partners: the old "Report for Jim". The
 * $95/hr line is the judgement, rows sort worst rate first and outstanding cash
 * is aged against the period end. Hours come from the hours table alone.
 */
/**
 * @property-read array<string, mixed> $figures
 * @property-read Collection<int, array<string, mixed>> $rows
 */
#[Title('Month End')]
class MonthEndReport extends Component
{
    use SortsRows;

    #[Url]
    public ?string $period = null;

    #[Url]
    public string $only = 'billing';

    #[Url]
    public string $sort = 'rateLive';

    #[Url]
    public string $direction = 'asc';

    /** @var array<int, string> */
    protected array $textColumns = ['jobName', 'developer'];

    public function mount(BillingEngine $engine): void
    {
        if (! in_array($this->period, $engine->periods(), true)) {
            $this->period = $engine->latestPeriod();
        }

        if (! array_key_exists($this->only, PeriodReports::Filters)) {
            $this->only = 'billing';
        }
    }

    /** Open the month clicked on the revenue chart, when it has a billing period. */
    public function openMonth(int $index): void
    {
        $month = $this->figures['series']['months'][$index] ?? null;

        if ($month && $month['period']) {
            $this->period = $month['period'];
        }
    }

    public function closePeriod(SetPeriodStatusAction $setPeriodStatus): void
    {
        $this->setStatus($setPeriodStatus, 'closed');
    }

    public function reopenPeriod(SetPeriodStatusAction $setPeriodStatus): void
    {
        $this->setStatus($setPeriodStatus, 'open');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function figures(): array
    {
        if (! $this->period) {
            return [];
        }

        return app(PeriodReports::class)->monthEnd($this->period, $this->only);
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function rows(): Collection
    {
        return $this->sortRows($this->figures['rows'] ?? collect());
    }

    protected function setStatus(SetPeriodStatusAction $setPeriodStatus, string $status): void
    {
        $this->authorize(Permission::EditBilling->value);

        if (! $this->period) {
            return;
        }

        $setPeriodStatus->execute($this->period, $status);

        app(BillingEngine::class)->load();
        unset($this->figures, $this->rows);
    }

    public function render(BillingEngine $engine): View
    {
        return view('livewire.month-end.report', [
            'periods' => array_reverse($engine->periods()),
            'engine' => $engine,
        ]);
    }
}

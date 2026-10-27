<?php

namespace App\Livewire;

use App\Billing\Spending;
use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/*
 * Where the money went, in a modal that Overview and Month End open with a
 * "show-spending" event carrying the months to cover. Staff time by person is
 * for those who manage staff costs; the Costs sheet lines are for anyone who
 * sees financials.
 */
/**
 * @property-read array<string, mixed> $breakdown
 */
class SpendingBreakdown extends Component
{
    public bool $open = false;

    /** @var array<int, string> */
    public array $months = [];

    /** @param  array<int, string>  $months */
    #[On('show-spending')]
    public function show(array $months): void
    {
        $this->authorize(Permission::ViewFinancials->value);

        $this->months = collect($months)
            ->filter(fn (mixed $month) => is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month))
            ->unique()
            ->sort()
            ->values()
            ->all();

        unset($this->breakdown);

        $this->open = $this->months !== [];
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function breakdown(): array
    {
        return app(Spending::class)->breakdown(
            $this->months,
            withStaff: auth()->user()->can(Permission::ManageStaffCosts->value),
        );
    }

    public function title(): string
    {
        if ($this->months === []) {
            return __('Where the money went');
        }

        $first = CarbonImmutable::createFromFormat('!Y-m', $this->months[0]);
        $last = CarbonImmutable::createFromFormat('!Y-m', end($this->months));

        $span = count($this->months) === 1
            ? $first->format('F Y')
            : ($first->year === $last->year
                ? $first->format('M').' – '.$last->format('M Y')
                : $first->format('M Y').' – '.$last->format('M Y'));

        return __('Where the money went — :span', ['span' => $span]);
    }

    public function render(): View
    {
        return view('livewire.spending-breakdown');
    }
}

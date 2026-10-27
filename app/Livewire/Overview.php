<?php

namespace App\Livewire;

use App\Billing\BillingEngine;
use App\Billing\PeriodReports;
use App\Billing\Spending;
use App\Enums\Permission;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * How the year is going: what was billed, what is still owed, what it cost
 * and what was left. Billing comes from month end, owed from Month End's
 * outstanding figure, and cost from the workbook's Costs sheet — or, for a
 * month the sheet has no row for, staff time at cost as a marked estimate.
 * Profit is only worked out over months that have both billing and cost, so a
 * month without one never reads as pure profit or pure loss.
 */
/**
 * @property-read Collection<int, int> $years
 * @property-read array<string, mixed> $figures
 */
#[Title('Overview')]
class Overview extends Component
{
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

    /** @return Collection<int, int> */
    #[Computed]
    public function years(): Collection
    {
        return collect(app(PeriodReports::class)->years());
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function figures(): array
    {
        $engine = app(BillingEngine::class);
        $reports = app(PeriodReports::class);
        $series = $reports->yearSeries($this->year);
        $prior = collect($reports->yearSeries($this->year - 1)['months']);

        $costs = app(Spending::class)->monthCosts(
            array_column($series['months'], 'key'),
            withStaff: auth()->user()->can(Permission::ManageStaffCosts->value),
        );

        $months = collect($series['months'])->map(fn (array $month) => [...$month, 'cost' => $costs[$month['key']]['basis'] ? $costs[$month['key']] : null]);
        $entered = $months->where('basis', 'actual');
        $upTo = $entered->last()['month'] ?? 12;

        $ytd = $entered->sum('billed');
        $priorSpan = $prior->whereNotNull('billed')->where('month', '<=', $upTo);
        $priorYtd = $entered->isNotEmpty() && $priorSpan->count() === $entered->count() ? $priorSpan->sum('billed') : null;

        $costed = $entered->whereNotNull('cost');
        $costTotal = $costed->sum(fn (array $month) => $month['cost']['total']);
        $costedBilled = $costed->sum('billed');
        $profit = $costed->isNotEmpty() ? $costedBilled - $costTotal : null;
        $estimated = $costed->filter(fn (array $month) => $month['cost']['basis'] === 'staff');

        $rated = $entered->filter(fn (array $month) => (bool) $month['hours']);
        $realized = $rated->sum('hours') ? $rated->sum('billed') / $rated->sum('hours') : null;

        $remaining = collect($engine->allSummaries())
            ->where('active', true)
            ->filter(fn (array $row) => Money::compare($row['balance'], 0) > 0);

        // Owed as at the newest thing the data knows of, so payments after the last month end count.
        $latest = collect($engine->periods())->max();
        $owedAsAt = collect($engine->allSummaries())->pluck('lastPaymentDate')->push($latest)->filter()->max();
        $owed = $latest
            ? $reports->outstanding($reports->monthEndRows($owedAsAt, $engine->periodReport($latest))->where('active', true))
            : ['total' => 0.0, 'jobs' => 0, 'clients' => [], 'bands' => []];

        $divisions = array_fill_keys(array_keys(PeriodReports::Divisions), 0.0);

        foreach ($engine->billedByJobPeriod() as $key => $amount) {
            [$period, $jobNumber] = explode('|', $key, 2);

            if (str_starts_with($period, (string) $this->year)) {
                $divisions[PeriodReports::divisionOf($engine->job($jobNumber)['division'] ?? null)] += (float) $amount;
            }
        }

        return [
            'months' => $months,
            'monthsEntered' => $entered->count(),
            'lastEntered' => $series['lastEntered'],
            'ytd' => $ytd,
            'priorYtd' => $priorYtd,
            'priorFull' => $prior->whereNotNull('billed')->count() === 12 ? $prior->sum('billed') : null,
            'runRate' => $series['runRate'],
            'projected' => $months->sum('revenue'),
            'realized' => $realized,
            'realizedHours' => $rated->sum('hours'),
            'owed' => $owed,
            'owedAsAt' => $owedAsAt,
            'owedLate' => collect($owed['bands'])->whereIn('key', ['a3', 'a4'])->sum('amount'),
            'cost' => $costTotal,
            'payroll' => $costed->sum(fn (array $month) => $month['cost']['payroll']),
            'overhead' => $costed->sum(fn (array $month) => $month['cost']['overhead']),
            'costedMonths' => $costed->count(),
            'spendingMonths' => $entered->pluck('key')->values()->all(),
            'costedBilled' => $costedBilled,
            'profit' => $profit,
            'margin' => $profit !== null && $costedBilled ? $profit / $costedBilled : null,
            'uncosted' => $entered->whereNull('cost')->pluck('label')->all(),
            'estimatedMonths' => $estimated->pluck('label')->all(),
            'estimatedUncostedHours' => $estimated->sum(fn (array $month) => $month['cost']['uncostedHours']),
            'costsWaiting' => $months->filter(fn (array $month) => $month['basis'] !== 'actual' && ($month['cost']['basis'] ?? null) === 'sheet')->pluck('label')->all(),
            'backlog' => $remaining->sum(fn (array $row) => (float) $row['balance']),
            'backlogJobs' => $remaining->count(),
            'divisions' => $divisions,
            'chart' => [
                'labels' => $months->pluck('label')->all(),
                'actual' => $months->pluck('billed')->all(),
                'runRate' => $months->map(fn (array $month) => $month['basis'] === 'runrate' ? $month['revenue'] : null)->all(),
                'cost' => $months->map(fn (array $month) => $month['basis'] === 'actual' && $month['cost'] ? $month['cost']['total'] : null)->all(),
                'profit' => $months->map(fn (array $month) => $month['basis'] === 'actual' && $month['cost'] ? round($month['billed'] - $month['cost']['total'], 2) : null)->all(),
            ],
            'ring' => [
                'labels' => array_keys($divisions),
                'values' => array_values($divisions),
                'colors' => array_slice(PeriodReports::Slots, 0, count($divisions)),
            ],
        ];
    }

    public function render(): View
    {
        return view('livewire.overview', ['slots' => PeriodReports::Slots]);
    }
}

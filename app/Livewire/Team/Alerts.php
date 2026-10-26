<?php

namespace App\Livewire\Team;

use App\Actions\Team\SetPlanRateAction;
use App\Billing\ProjectBurn;
use App\Enums\Permission;
use App\Models\WorkbookImport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read array{rows: Collection<int, array<string, mixed>>, noFee: Collection<int, array<string, mixed>>, watched: int} $report
 */
#[Title('Project Alerts')]
class Alerts extends Component
{
    public const Windows = [1 => 'last 1 month', 2 => 'last 2 months', 3 => 'last 3 months', 6 => 'last 6 months', 0 => 'any time'];

    #[Url]
    public int $months = 3;

    #[Url]
    public bool $paused = false;

    #[Url]
    public bool $onTrack = false;

    public string $planRate = '';

    public bool $editingRate = false;

    /**
     * Active projects with hours in the window, measured against their fee.
     * Fully billed projects only show while they still log hours: working
     * beyond the fee is exactly what this page is for.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, noFee: Collection<int, array<string, mixed>>, watched: int}
     */
    #[Computed]
    public function report(): array
    {
        $burn = app(ProjectBurn::class);
        $months = array_key_exists($this->months, self::Windows) ? $this->months : 3;
        $from = $burn->windowStart($months);
        $asOf = $burn->asOf();
        $statuses = $this->paused ? ['active', 'paused'] : ['active'];

        $rows = collect();
        $noFee = collect();

        foreach (array_keys($burn->engine()->jobs()) as $jobNumber) {
            $status = $burn->effectiveStatus($jobNumber);

            if (! in_array($status, $statuses, true)) {
                continue;
            }

            $recent = $burn->hoursSince($jobNumber, $from);

            if ($months > 0 && $recent <= 0) {
                continue;
            }

            $project = $burn->for($jobNumber);
            $row = [
                ...$project,
                'recentHours' => $recent,
                'monthsAgo' => $project['lastHoursPeriod'] && $asOf
                    ? (int) CarbonImmutable::parse($project['lastHoursPeriod'])->startOfMonth()->diffInMonths(CarbonImmutable::parse($asOf)->startOfMonth())
                    : null,
            ];

            if ($project['fee'] === null) {
                if ($recent > 0) {
                    $noFee->push($row);
                }

                continue;
            }

            $rows->push([
                ...$row,
                'flag' => match (true) {
                    $project['used'] >= 1 => 'over',
                    in_array($project['level'], ['watch', 'over'], true) => 'warn',
                    default => 'ok',
                },
            ]);
        }

        return [
            'rows' => $rows->sortByDesc('used')->values(),
            'noFee' => $noFee->sortByDesc('hours')->values(),
            'watched' => $rows->count(),
        ];
    }

    public function editRate(): void
    {
        $this->authorize(Permission::EditTeam->value);

        $this->planRate = (string) app(ProjectBurn::class)->planRate();
        $this->resetValidation();
        $this->editingRate = true;
    }

    public function saveRate(SetPlanRateAction $setPlanRate): void
    {
        $this->authorize(Permission::EditTeam->value);

        $this->resetValidation();
        $setPlanRate->execute($this->planRate);

        $this->editingRate = false;
        unset($this->report);
    }

    public function render(): View
    {
        $burn = app(ProjectBurn::class);
        $report = $this->report;
        $flagged = $report['rows']->whereIn('flag', ['over', 'warn']);
        $over = $report['rows']->where('flag', 'over');
        $rate = $burn->planRate();

        return view('livewire.team.alerts', [
            'asOf' => $burn->asOf(),
            'uploadedAt' => WorkbookImport::latest()->value('created_at'),
            'rate' => $rate,
            'windows' => self::Windows,
            'shown' => $this->onTrack ? $report['rows'] : $flagged->values(),
            'overCount' => $over->count(),
            'warnCount' => $report['rows']->where('flag', 'warn')->count(),
            'overrun' => $over->sum(fn (array $row) => max(0, $row['hours'] * $rate - $row['fee'])),
            'flaggedCount' => $flagged->count(),
            'canEdit' => auth()->user()->can(Permission::EditTeam->value),
        ]);
    }
}

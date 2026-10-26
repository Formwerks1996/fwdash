<?php

namespace App\Billing;

use App\Enums\Phase;
use App\Models\AppSetting;
use App\Models\ProjectStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;

/*
 * How fast a project is eating its fee, at project level.
 *
 * The workbook gives hours per project per month and nothing finer (no phase,
 * no person), so "budget used" is every hour logged priced at one plan rate,
 * against the phase fees. The plan rate is a value, not anyone's pay.
 *
 * One rule colours both Project Alerts and PM Desk, so they never disagree:
 *   over  : hours have used the whole fee
 *   watch : 80% used, or hours running more than 15 points ahead of billing
 *   ok    : otherwise
 * plus "none" (no fee to measure against) and "idle" (no hours yet).
 */

/**
 * @phpstan-type Burn array{
 *     jobNumber: string, jobName: string, developer: ?string, division: ?string, lead: ?string,
 *     status: string, fee: ?float, billedFee: float, progress: ?float, hours: float, feeHours: ?float, used: ?float,
 *     level: string, hoursByMonth: array<string, float>, lastHoursPeriod: ?string, currentPhase: ?string,
 *     phasesBilled: int, phaseCount: int,
 *     phases: array<int, array{phase: string, fee: float, billed: float, progress: float, feeHours: float, state: string}>
 * }
 */
class ProjectBurn
{
    public const PlanRateKey = 'alerts.plan_rate';

    public const DefaultPlanRate = 95.0;

    public const WatchAt = 0.8;

    public const AheadBy = 0.15;

    /** "Spend ahead" chip: hours this far ahead of billing. */
    public const SpendAheadBy = 0.25;

    protected ?float $planRate = null;

    /** @var array<string, string>|null */
    protected ?array $statusOverrides = null;

    /** @var array<string, Burn> */
    protected array $cache = [];

    public function __construct(protected BillingEngine $engine) {}

    public function engine(): BillingEngine
    {
        return $this->engine;
    }

    public function planRate(): float
    {
        if ($this->planRate !== null) {
            return $this->planRate;
        }

        $stored = AppSetting::find(self::PlanRateKey)?->value;

        return $this->planRate = is_numeric($stored) && (float) $stored > 0 ? (float) $stored : self::DefaultPlanRate;
    }

    /** The latest month with hours: every window is anchored here, never on today, because hours lag the upload. */
    public function asOf(): ?string
    {
        return array_key_last($this->engine->hoursByPeriod());
    }

    /** active, paused or closed: a person's status wins over the Active flag. */
    public function effectiveStatus(string $jobNumber): string
    {
        $this->statusOverrides ??= ProjectStatus::pluck('status', 'job_number')->all();

        return $this->statusOverrides[$jobNumber]
            ?? (($this->engine->job($jobNumber)['active'] ?? false) ? 'active' : 'closed');
    }

    /** Month-end that starts a window of N months ending at the as-of month; null = any time. */
    public function windowStart(int $months): ?string
    {
        $asOf = $this->asOf();

        if ($months <= 0 || $asOf === null) {
            return null;
        }

        return CarbonImmutable::parse($asOf)->startOfMonth()->subMonthsNoOverflow($months - 1)->endOfMonth()->toDateString();
    }

    /** Hours logged from a month-end on (excluding the opening balance); null = all months. */
    public function hoursSince(string $jobNumber, ?string $from): float
    {
        $hours = collect($this->engine->hoursByPeriod($jobNumber))
            ->filter(fn (float $value, string $period) => $from === null || $period >= $from)
            ->sum();

        return round((float) $hours, 2);
    }

    public static function level(?float $used, ?float $progress, float $hours): string
    {
        if ($used === null) {
            return 'none';
        }

        if ($hours <= 0) {
            return 'idle';
        }

        if ($used >= 1) {
            return 'over';
        }

        if ($used >= self::WatchAt || ($progress !== null && $used - $progress > self::AheadBy)) {
            return 'watch';
        }

        return 'ok';
    }

    /** A burn under 100% never rounds up to read "100%". */
    public static function burnPct(?float $used): string
    {
        if ($used === null) {
            return '—';
        }

        return ($used < 1 ? floor($used * 100) : round($used * 100)).'%';
    }

    /** @return Burn */
    public function for(string $jobNumber): array
    {
        if (isset($this->cache[$jobNumber])) {
            return $this->cache[$jobNumber];
        }

        $summary = $this->engine->jobSummary($jobNumber);
        $rate = $this->planRate();

        $phases = collect($this->engine->jobFeeDetail($jobNumber))
            ->filter(fn (array $row) => $row['phase'] !== Phase::Misc->value && $row['fee'] !== null && Money::compare($row['fee'], 0) > 0)
            ->map(fn (array $row) => [
                'phase' => (string) $row['phase'],
                'fee' => Money::toFloat($row['fee']),
                'billed' => Money::toFloat($row['billed']),
            ])
            ->values();

        $currentPhase = $phases->first(fn (array $row) => $row['billed'] < $row['fee'] - 0.005)['phase'] ?? null;

        $fee = $phases->sum('fee') ?: null;
        $billedFee = (float) $phases->sum('billed');
        $progress = $fee ? min(1.0, $billedFee / $fee) : null;
        $hours = (float) $summary['hoursTotal'];
        $feeHours = $fee ? $fee / $rate : null;
        $used = $feeHours ? $hours / $feeHours : null;
        $hoursByMonth = $this->engine->hoursByPeriod($jobNumber);

        return $this->cache[$jobNumber] = [
            'jobNumber' => $jobNumber,
            'jobName' => (string) $summary['jobName'],
            'developer' => $summary['developer'],
            'division' => $summary['division'],
            'lead' => $summary['lead'],
            'status' => $this->effectiveStatus($jobNumber),
            'fee' => $fee,
            'billedFee' => $billedFee,
            'progress' => $progress,
            'hours' => $hours,
            'feeHours' => $feeHours,
            'used' => $used,
            'level' => self::level($used, $progress, $hours),
            'hoursByMonth' => $hoursByMonth,
            'lastHoursPeriod' => collect($hoursByMonth)->filter(fn (float $value) => $value > 0)->keys()->last(),
            'currentPhase' => $currentPhase,
            'phasesBilled' => $phases->filter(fn (array $row) => $row['billed'] >= $row['fee'] - 0.005)->count(),
            'phaseCount' => $phases->count(),
            'phases' => $phases
                ->map(fn (array $row) => [
                    ...$row,
                    'progress' => min(1.0, $row['billed'] / $row['fee']),
                    'feeHours' => $row['fee'] / $rate,
                    'state' => match (true) {
                        $row['billed'] >= $row['fee'] - 0.005 => 'complete',
                        $row['phase'] === $currentPhase => 'current',
                        $row['billed'] <= 0 => 'not started',
                        default => 'billing',
                    },
                ])
                ->all(),
        ];
    }
}

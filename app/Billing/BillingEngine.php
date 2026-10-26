<?php

namespace App\Billing;

use App\Enums\Phase;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * The billing maths, reproduced from the invoicing workbook.
 *
 * Loads every billing table once and derives all figures from the stored
 * inputs, so nothing is computed at write time and a corrected fee or rate
 * flows straight through. Each rule names the Excel behaviour it reproduces.
 *
 * Vocabulary: "phase billing" is a milestone draw (fee x % complete, or a typed
 * dollar figure); "hourly billing" is time and materials, which the workbook
 * calls Extras. Both eat the contract balance.
 */
class BillingEngine
{
    /** @var array<string, array<string, mixed>> */
    protected array $jobs = [];

    /** @var array<string, array<string, ?string>> */
    protected array $fees = [];

    /** @var array<string, array<string, ?string>> */
    protected array $feeNotes = [];

    /** @var array<string, array<string, string>> */
    protected array $overrides = [];

    /** @var array<string, array<string, mixed>> */
    protected array $staff = [];

    /** @var array<string, ?string> */
    protected array $rateTitles = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $entries = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $hourLines = [];

    /** @var array<string, string> keyed "period|job" */
    protected array $hourlyOverrides = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $payments = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $consultants = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $hours = [];

    /** @var array<string, string> */
    protected array $periodStatus = [];

    /** @var array<string, array<string, mixed>> */
    protected array $summaryCache = [];

    /** @var array<string, array<string, mixed>> keyed "period|job|phase" */
    protected array $entryIndex = [];

    public function __construct()
    {
        $this->load();
    }

    public function load(): void
    {
        $this->summaryCache = [];

        $this->jobs = DB::table('projects')
            ->orderBy('job_name')
            ->get()
            ->mapWithKeys(fn (object $job) => [$job->job_number => (array) $job])
            ->all();

        $this->fees = [];
        $this->feeNotes = [];

        foreach (DB::table('project_phase_fees')->get() as $fee) {
            $this->fees[$fee->job_number][$fee->phase_code] = $fee->fee;
            $this->feeNotes[$fee->job_number][$fee->phase_code] = $fee->note;
        }

        $this->overrides = [];

        foreach (DB::table('project_rate_overrides')->get() as $override) {
            $this->overrides[$override->job_number][$override->rate_title] = $override->rate;
        }

        $this->staff = DB::table('staff')
            ->get()
            ->mapWithKeys(fn (object $person) => [$person->name => (array) $person])
            ->all();

        $this->rateTitles = DB::table('rate_titles')
            ->pluck('default_charge_out', 'title')
            ->all();

        $this->entries = $this->groupByJob(DB::table('month_entries')->orderBy('period')->get());

        $this->entryIndex = [];

        foreach ($this->entries as $jobNumber => $entries) {
            foreach ($entries as $entry) {
                $this->entryIndex["{$entry['period']}|{$jobNumber}|{$entry['phase_code']}"] = $entry;
            }
        }
        $this->hourLines = $this->groupByJob(DB::table('hour_lines')->orderBy('id')->get());
        $this->payments = $this->groupByJob(DB::table('payments')->orderBy('paid_on')->orderBy('id')->get(), 'paid_on');
        $this->consultants = $this->groupByJob(DB::table('consultant_fees')->orderBy('period')->orderBy('id')->get());
        $this->hours = $this->groupByJob(DB::table('job_hours')->orderBy('period')->get());

        $this->hourlyOverrides = [];

        foreach (DB::table('month_hourly')->get() as $override) {
            $this->hourlyOverrides[$this->period($override->period).'|'.$override->job_number] = $override->amount;
        }

        $this->periodStatus = DB::table('billing_periods')
            ->orderBy('period')
            ->get()
            ->mapWithKeys(fn (object $period) => [$this->period($period->period) => $period->status])
            ->all();
    }

    /** @return array<string, array<string, mixed>> */
    public function jobs(): array
    {
        return $this->jobs;
    }

    /** @return array<string, mixed>|null */
    public function job(string $jobNumber): ?array
    {
        return $this->jobs[$jobNumber] ?? null;
    }

    /** @return array<int, string> */
    public function periods(): array
    {
        return array_keys($this->periodStatus);
    }

    public function periodStatus(string $period): string
    {
        return $this->periodStatus[$period] ?? 'open';
    }

    public function latestPeriod(): ?string
    {
        return array_key_last($this->periodStatus);
    }

    /** @return array{0: ?string, 1: string} */
    public function resolveRate(string $jobNumber, string $staffName): array
    {
        $person = $this->staff[$staffName] ?? null;

        if (! $person) {
            return [null, 'unknown staff'];
        }

        $title = $person['rate_title'];
        $jobRate = $this->overrides[$jobNumber][$title] ?? null;

        if ($jobRate && ! Money::isZero($jobRate)) {
            return [$jobRate, 'job override'];
        }

        if ($person['charge_out'] && ! Money::isZero($person['charge_out'])) {
            return [$person['charge_out'], 'staff charge out'];
        }

        $default = $this->rateTitles[$title] ?? null;

        if ($default && ! Money::isZero($default)) {
            return [$default, 'rate title default'];
        }

        return [null, 'unresolved'];
    }

    /**
     * The fee as it stood when the month was billed, so a contract amendment never restates an invoice.
     *
     * @param  array<string, mixed>|null  $entry
     */
    public function phaseFee(string $jobNumber, string $phase, ?array $entry = null): ?string
    {
        if ($entry && $entry['fee_at_entry'] !== null) {
            return $entry['fee_at_entry'];
        }

        return $this->fees[$jobNumber][$phase] ?? null;
    }

    /**
     * A typed dollar figure wins over a percentage: it is how the CA monthly draw is billed.
     *
     * @param  array<string, mixed>  $entry
     */
    public function entryBilled(array $entry): string
    {
        if ($entry['amount'] !== null) {
            return Money::round($entry['amount']);
        }

        if ($entry['pct_complete'] === null || Money::isZero($entry['pct_complete'])) {
            return '0.00';
        }

        $fee = $this->phaseFee($entry['job_number'], $entry['phase_code'], $entry);

        return Money::round(Money::mul($fee ?? 0, $entry['pct_complete']));
    }

    /** @param  array<string, mixed>|null  $entry */
    public function entryBasis(?array $entry): string
    {
        if (! $entry) {
            return 'none';
        }

        if ($entry['amount'] !== null) {
            return 'amount';
        }

        return $entry['pct_complete'] !== null && ! Money::isZero($entry['pct_complete'])
            ? 'percent'
            : 'none';
    }

    public function phaseBilledToDate(string $jobNumber, string $phase, ?string $through = null): string
    {
        return Money::round(Money::sum(
            collect($this->entries[$jobNumber] ?? [])
                ->filter(fn (array $entry) => $entry['phase_code'] === $phase)
                ->reject(fn (array $entry) => $through && $entry['period'] > $through)
                ->map(fn (array $entry) => $this->entryBilled($entry)),
        ));
    }

    /** Excel's BilledToDate: SUMIFS over months strictly before this one. */
    public function phaseBilledBefore(string $jobNumber, string $phase, string $period): string
    {
        return Money::round(Money::sum(
            collect($this->entries[$jobNumber] ?? [])
                ->filter(fn (array $entry) => $entry['phase_code'] === $phase && $entry['period'] < $period)
                ->map(fn (array $entry) => $this->entryBilled($entry)),
        ));
    }

    /**
     * A typed amount wins over hours x rate; an amount of 0 means worked but not billed.
     *
     * @param  array<string, mixed>  $line
     */
    public function lineTotal(array $line): string
    {
        if ($line['amount'] !== null) {
            return Money::round($line['amount']);
        }

        if ($line['hours'] === null || $line['rate'] === null) {
            return '0.00';
        }

        return Money::round(Money::mul($line['hours'], $line['rate']));
    }

    /** A job-month override wins over the staff lines: a flat fee, a cap or a top-up. */
    public function hourlyBilled(string $jobNumber, string $period): string
    {
        $override = $this->hourlyOverrides["{$period}|{$jobNumber}"] ?? null;

        if ($override !== null) {
            return Money::round($override);
        }

        return Money::round(Money::sum(
            collect($this->hourLines[$jobNumber] ?? [])
                ->filter(fn (array $line) => $line['period'] === $period)
                ->map(fn (array $line) => $this->lineTotal($line)),
        ));
    }

    public function hourlyOverride(string $jobNumber, string $period): ?string
    {
        return $this->hourlyOverrides["{$period}|{$jobNumber}"] ?? null;
    }

    /** @return array{phaseBilled: string, hourlyBilled: string, billed: string} */
    public function jobPeriodTotals(string $jobNumber, string $period): array
    {
        $phase = Money::sum(
            collect($this->entries[$jobNumber] ?? [])
                ->filter(fn (array $entry) => $entry['period'] === $period)
                ->map(fn (array $entry) => $this->entryBilled($entry)),
        );

        $hourly = $this->hourlyBilled($jobNumber, $period);

        return [
            'phaseBilled' => Money::round($phase),
            'hourlyBilled' => Money::round($hourly),
            'billed' => Money::round(Money::add($phase, $hourly)),
        ];
    }

    /**
     * The partners' report figures for one job ("Report for Jim").
     *
     * @return array<string, mixed>
     */
    public function jobSummary(string $jobNumber, ?string $through = null): array
    {
        $cacheKey = "{$jobNumber}|{$through}";

        if (isset($this->summaryCache[$cacheKey])) {
            return $this->summaryCache[$cacheKey];
        }

        $job = $this->jobs[$jobNumber] ?? [];
        $fees = $this->fees[$jobNumber] ?? [];

        $contract = Money::round(Money::sum(array_filter($fees, fn (mixed $fee) => $fee !== null && ! Money::isZero($fee))));

        $phaseBilled = '0';
        $caBilled = '0';
        $byPhase = array_fill_keys(Phase::codes(), '0');

        foreach ($this->entries[$jobNumber] ?? [] as $entry) {
            if ($through && $entry['period'] > $through) {
                continue;
            }

            $amount = $this->entryBilled($entry);
            $phaseBilled = Money::add($phaseBilled, $amount);

            if (array_key_exists($entry['phase_code'], $byPhase)) {
                $byPhase[$entry['phase_code']] = Money::add($byPhase[$entry['phase_code']], $amount);
            }

            if ($entry['phase_code'] === Phase::Ca->value) {
                $caBilled = Money::add($caBilled, $amount);
            }
        }

        $hourlyBilled = Money::sum(
            collect($this->billedPeriods($jobNumber))
                ->reject(fn (string $period) => $through && $period > $through)
                ->map(fn (string $period) => $this->hourlyBilled($jobNumber, $period)),
        );

        $billed = Money::round(Money::add($phaseBilled, $hourlyBilled));
        $balance = Money::round(Money::maxZero(Money::sub($contract, $billed)));
        $balanceCa = Money::round(Money::maxZero(Money::sub($fees[Phase::Ca->value] ?? 0, $caBilled)));
        $balanceDesign = Money::round(Money::maxZero(Money::sub($balance, $balanceCa)));

        $payments = collect($this->payments[$jobNumber] ?? [])
            ->reject(fn (array $payment) => $through && $payment['paid_on'] > $through);

        $paymentsTotal = Money::round(Money::sum($payments->pluck('amount')));
        $lastPayment = $payments->reduce(
            fn (?array $latest, array $payment) => $latest === null || $payment['paid_on'] > $latest['paid_on'] ? $payment : $latest,
        );

        $consultantTotal = Money::round(Money::sum(
            collect($this->consultants[$jobNumber] ?? [])
                ->reject(fn (array $fee) => $through && $fee['period'] > $through)
                ->pluck('total'),
        ));

        $hours = collect($this->hours[$jobNumber] ?? [])
            ->reject(fn (array $row) => $through && $row['period'] > $through);

        $hoursTotal = round(Money::toFloat(Money::sum($hours->pluck('hours'))), 2);
        $monthlyHours = round(Money::toFloat(Money::sum($hours->where('source', '!=', 'prior')->pluck('hours'))), 2);
        $openingHours = round(Money::toFloat(Money::sum($hours->where('source', 'prior')->pluck('hours'))), 2);

        return $this->summaryCache[$cacheKey] = [
            'jobNumber' => $jobNumber,
            'jobName' => $job['job_name'] ?? $jobNumber,
            'developer' => $job['developer'] ?? null,
            'division' => $job['division'] ?? null,
            'lead' => $job['project_lead'] ?? null,
            'active' => (bool) ($job['active'] ?? false),
            'caPerMonth' => $job['ca_per_month'] ?? null,
            'ifcDate' => $job['ifc_date'] ?? null,
            'contract' => $contract,
            'billed' => $billed,
            'phaseBilled' => Money::round($phaseBilled),
            'hourlyBilled' => Money::round($hourlyBilled),
            'billedPct' => Money::isZero($contract) ? null : Money::toFloat($billed) / Money::toFloat($contract),
            'balance' => $balance,
            'balanceDesign' => $balanceDesign,
            'balanceCA' => $balanceCa,
            'byPhase' => array_map(fn (string $amount) => Money::round($amount), $byPhase),
            'paymentsTotal' => $paymentsTotal,
            'outstanding' => Money::round(Money::sub($billed, $paymentsTotal)),
            'lastPaymentDate' => $lastPayment['paid_on'] ?? null,
            'lastPaymentAmount' => $lastPayment['amount'] ?? null,
            'consultantTotal' => $consultantTotal,
            'hoursTotal' => $hoursTotal,
            'monthlyHoursTotal' => $monthlyHours,
            'openingHours' => $openingHours ?: null,
            'ratePerHour' => $hoursTotal
                ? round(Money::toFloat(Money::sub($billed, $consultantTotal)) / $hoursTotal, 2)
                : null,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function allSummaries(?string $through = null): array
    {
        return collect(array_keys($this->jobs))
            ->mapWithKeys(fn (string $jobNumber) => [$jobNumber => $this->jobSummary($jobNumber, $through)])
            ->all();
    }

    /** @return array<string, mixed> */
    public function totals(?string $through = null): array
    {
        $rows = collect($this->allSummaries($through));

        $keys = ['contract', 'billed', 'phaseBilled', 'hourlyBilled', 'balance', 'balanceDesign', 'balanceCA', 'paymentsTotal', 'outstanding', 'consultantTotal'];

        $totals = collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => Money::round(Money::sum($rows->pluck($key)))])
            ->all();

        return [
            ...$totals,
            'hoursTotal' => round($rows->sum('hoursTotal'), 2),
            'projectCount' => $rows->count(),
            'billedPct' => Money::isZero($totals['contract']) ? null : Money::toFloat($totals['billed']) / Money::toFloat($totals['contract']),
            'projectsOverContract' => $rows->filter(fn (array $row) => ! Money::isZero($row['contract']) && Money::compare($row['billed'], $row['contract']) > 0)->count(),
            'projectsNoContract' => $rows->filter(fn (array $row) => Money::isZero($row['contract']) && ! Money::isZero($row['billed']))->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function jobFeeDetail(string $jobNumber): array
    {
        return collect(Phase::codes())
            ->map(function (string $phase) use ($jobNumber) {
                $fee = $this->fees[$jobNumber][$phase] ?? null;
                $billed = $this->phaseBilledToDate($jobNumber, $phase);

                $amended = collect($this->entries[$jobNumber] ?? [])
                    ->filter(fn (array $entry) => $entry['phase_code'] === $phase
                        && $entry['fee_at_entry'] !== null
                        && $fee !== null
                        && abs(Money::toFloat(Money::sub($entry['fee_at_entry'], $fee))) > 0.005)
                    ->count();

                return [
                    'phase' => $phase,
                    'fee' => $fee,
                    'note' => $this->feeNotes[$jobNumber][$phase] ?? null,
                    'billed' => $billed,
                    'pctComplete' => $fee && ! Money::isZero($fee) ? Money::toFloat($billed) / Money::toFloat($fee) : null,
                    'balance' => Money::round(Money::maxZero(Money::sub($fee ?? 0, $billed))),
                    'billedAtOtherFee' => $amended,
                ];
            })
            ->all();
    }

    /**
     * Month-by-month history for one job, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function jobHistory(string $jobNumber): array
    {
        $hoursByPeriod = collect($this->hours[$jobNumber] ?? [])
            ->where('source', '!=', 'prior')
            ->groupBy('period')
            ->map(fn ($rows) => Money::sum($rows->pluck('hours')));

        $consultantsByPeriod = collect($this->consultants[$jobNumber] ?? [])
            ->groupBy('period')
            ->map(fn ($rows) => Money::sum($rows->pluck('total')));

        $paymentsByPeriod = collect($this->payments[$jobNumber] ?? [])
            ->groupBy(fn (array $payment) => static::monthEnd($payment['paid_on']));

        $periods = collect($this->billedPeriods($jobNumber))
            ->merge($consultantsByPeriod->keys())
            ->merge($hoursByPeriod->keys())
            ->merge($paymentsByPeriod->keys())
            ->unique()
            ->sort()
            ->values();

        $running = '0';
        $months = [];

        foreach ($periods as $period) {
            $totals = $this->jobPeriodTotals($jobNumber, $period);
            $running = Money::add($running, $totals['billed']);
            $hours = round(Money::toFloat($hoursByPeriod[$period] ?? 0), 2);
            $payments = $paymentsByPeriod[$period] ?? collect();

            $phases = collect($this->entries[$jobNumber] ?? [])
                ->filter(fn (array $entry) => $entry['period'] === $period)
                ->map(fn (array $entry) => [
                    'phase' => $entry['phase_code'],
                    'pct' => $entry['pct_complete'],
                    'amount' => $entry['amount'],
                    'feeAtEntry' => $entry['fee_at_entry'],
                    'billed' => $this->entryBilled($entry),
                    'note' => $entry['note'],
                ])
                ->reject(fn (array $row) => Money::isZero($row['billed']) && $row['pct'] === null && $row['amount'] === null)
                ->sortBy(fn (array $row) => Phase::seqOf($row['phase']))
                ->values()
                ->all();

            $months[] = [
                'period' => $period,
                'status' => $this->periodStatus($period),
                ...$totals,
                'billedToDate' => Money::round($running),
                'hours' => $hours,
                'ratePerHour' => $hours ? round(Money::toFloat($totals['billed']) / $hours, 2) : null,
                'consultants' => Money::round($consultantsByPeriod[$period] ?? 0),
                'paymentsTotal' => Money::round(Money::sum($payments->pluck('amount'))),
                'phases' => $phases,
                'hourLines' => collect($this->hourLines[$jobNumber] ?? [])
                    ->filter(fn (array $line) => $line['period'] === $period)
                    ->map(fn (array $line) => [...$line, 'total' => $this->lineTotal($line)])
                    ->values()
                    ->all(),
                'payments' => $payments->values()->all(),
                'hourlyOverride' => $this->hourlyOverride($jobNumber, $period),
            ];
        }

        return array_reverse($months);
    }

    /** @return array{phaseBilled: string, hourlyBilled: string, billed: string, jobCount: int} */
    public function periodTotals(string $period): array
    {
        $phase = '0';
        $hourly = '0';
        $jobs = [];

        foreach ($this->entries as $jobNumber => $entries) {
            foreach ($entries as $entry) {
                if ($entry['period'] !== $period) {
                    continue;
                }

                $amount = $this->entryBilled($entry);
                $phase = Money::add($phase, $amount);

                if (! Money::isZero($amount)) {
                    $jobs[$jobNumber] = true;
                }
            }
        }

        foreach ($this->hourlyJobsIn($period) as $jobNumber) {
            $amount = $this->hourlyBilled($jobNumber, $period);
            $hourly = Money::add($hourly, $amount);

            if (! Money::isZero($amount)) {
                $jobs[$jobNumber] = true;
            }
        }

        return [
            'phaseBilled' => Money::round($phase),
            'hourlyBilled' => Money::round($hourly),
            'billed' => Money::round(Money::add($phase, $hourly)),
            'jobCount' => count($jobs),
        ];
    }

    public function hoursIn(string $jobNumber, string $period): float
    {
        return round(Money::toFloat(Money::sum(
            collect($this->hours[$jobNumber] ?? [])
                ->filter(fn (array $row) => $row['period'] === $period && $row['source'] !== 'prior')
                ->pluck('hours'),
        )), 2);
    }

    /** @return array<string, float> hours by period, excluding the pre-system opening balance */
    public function hoursByPeriod(?string $jobNumber = null): array
    {
        $rows = $jobNumber
            ? collect($this->hours[$jobNumber] ?? [])
            : collect($this->hours)->flatten(1);

        return $rows
            ->where('source', '!=', 'prior')
            ->groupBy('period')
            ->map(fn ($group) => round(Money::toFloat(Money::sum($group->pluck('hours'))), 2))
            ->sortKeys()
            ->all();
    }

    /** @return array<string, string> payments summed by "YYYY-MM" of the date received */
    public function paymentsByMonth(): array
    {
        return collect($this->payments)
            ->flatten(1)
            ->groupBy(fn (array $payment) => substr($payment['paid_on'], 0, 7))
            ->map(fn ($group) => Money::round(Money::sum($group->pluck('amount'))))
            ->sortKeys()
            ->all();
    }

    /** @return array<string, string> consultant fees summed by "YYYY-MM" of the period */
    public function consultantsByMonth(): array
    {
        return collect($this->consultants)
            ->flatten(1)
            ->groupBy(fn (array $fee) => substr($fee['period'], 0, 7))
            ->map(fn ($group) => Money::round(Money::sum($group->pluck('total'))))
            ->all();
    }

    /** @return array<string, string> billed per job in each period, keyed "period|job" */
    public function billedByJobPeriod(): array
    {
        $billed = [];

        foreach ($this->entries as $jobNumber => $entries) {
            foreach ($entries as $entry) {
                $key = "{$entry['period']}|{$jobNumber}";
                $billed[$key] = Money::add($billed[$key] ?? '0', $this->entryBilled($entry));
            }
        }

        foreach ($this->hourLines as $jobNumber => $lines) {
            foreach (collect($lines)->pluck('period')->unique() as $period) {
                $billed["{$period}|{$jobNumber}"] = Money::add($billed["{$period}|{$jobNumber}"] ?? '0', $this->hourlyBilled($jobNumber, $period));
            }
        }

        foreach (array_keys($this->hourlyOverrides) as $key) {
            [$period, $jobNumber] = explode('|', $key, 2);

            if (collect($this->hourLines[$jobNumber] ?? [])->contains('period', $period)) {
                continue;
            }

            $billed[$key] = Money::add($billed[$key] ?? '0', $this->hourlyBilled($jobNumber, $period));
        }

        return $billed;
    }

    /** @return array<string, mixed>|null */
    public function entryAt(string $period, string $jobNumber, string $phase): ?array
    {
        return $this->entryIndex["{$period}|{$jobNumber}|{$phase}"] ?? null;
    }

    /**
     * Job numbers ordered by job name, case-insensitively, so every list sorts
     * the same way whichever database collation loaded them.
     *
     * @return array<int, string>
     */
    public function jobNumbersByName(): array
    {
        $jobNumbers = array_map('strval', array_keys($this->jobs));

        usort($jobNumbers, fn (string $left, string $right) => strcasecmp(
            (string) ($this->jobs[$left]['job_name'] ?? $left),
            (string) ($this->jobs[$right]['job_name'] ?? $right),
        ));

        return $jobNumbers;
    }

    /**
     * Jobs with anything billed in the period: the Billing Setup sheet's "Report = Y".
     *
     * @return array<int, string>
     */
    public function periodJobs(string $period): array
    {
        return array_values(array_filter(
            $this->jobNumbersByName(),
            fn (string $jobNumber) => ! Money::isZero($this->jobPeriodTotals($jobNumber, $period)['billed']),
        ));
    }

    /**
     * The jobs to work through at month end: time logged this month, anything
     * already entered, or a CA monthly draw due on an active job.
     *
     * @return array{period: string, status: string, billed: string, outstandingCount: int, jobs: array<int, array<string, mixed>>}
     */
    public function worklist(string $period): array
    {
        $withHours = [];

        foreach ($this->hours as $jobNumber => $rows) {
            foreach ($rows as $row) {
                if ($row['period'] === $period && $row['source'] !== 'prior') {
                    $withHours[$jobNumber] = Money::add($withHours[$jobNumber] ?? '0', $row['hours']);
                }
            }
        }

        $entered = [];

        foreach ($this->entries as $jobNumber => $entries) {
            foreach ($entries as $entry) {
                if ($entry['period'] === $period && ! Money::isZero($this->entryBilled($entry))) {
                    $entered[$jobNumber] = true;
                }
            }
        }

        foreach ($this->hourLines as $jobNumber => $lines) {
            foreach ($lines as $line) {
                if ($line['period'] === $period) {
                    $entered[$jobNumber] = true;
                }
            }
        }

        $caDue = [];

        foreach ($this->jobs as $jobNumber => $job) {
            $caFee = $this->fees[$jobNumber][Phase::Ca->value] ?? null;

            if ($job['active'] && $job['ca_per_month'] && ! Money::isZero($job['ca_per_month']) && $caFee !== null && ! Money::isZero($caFee)) {
                $caDue[$jobNumber] = true;
            }
        }

        $rows = [];

        foreach ($this->jobNumbersByName() as $jobNumber) {
            if (! isset($withHours[$jobNumber]) && ! isset($entered[$jobNumber]) && ! isset($caDue[$jobNumber])) {
                continue;
            }

            $job = $this->jobs[$jobNumber];
            $totals = $this->jobPeriodTotals($jobNumber, $period);

            $rows[] = [
                'jobNumber' => $jobNumber,
                'jobName' => $job['job_name'],
                'developer' => $job['developer'],
                'division' => $job['division'],
                'lead' => $job['project_lead'],
                'active' => (bool) $job['active'],
                'caPerMonth' => $job['ca_per_month'],
                'hoursLogged' => round(Money::toFloat($withHours[$jobNumber] ?? 0), 2),
                'reasons' => array_values(array_filter([
                    isset($withHours[$jobNumber]) ? 'hours logged' : null,
                    isset($entered[$jobNumber]) ? 'already entered' : null,
                    isset($caDue[$jobNumber]) ? 'CA draw due' : null,
                ])),
                'done' => ! Money::isZero($totals['billed']),
                ...$totals,
            ];
        }

        return [
            'period' => $period,
            'status' => $this->periodStatus($period),
            'billed' => Money::round(Money::sum(array_column($rows, 'billed'))),
            'outstandingCount' => count(array_filter($rows, fn (array $row) => ! $row['done'])),
            'jobs' => $rows,
        ];
    }

    /**
     * One job-month: all eleven phase rows, the hour lines and the totals.
     *
     * @return array<string, mixed>
     */
    public function periodGrid(string $period, string $jobNumber): array
    {
        $phases = [];

        foreach (Phase::codes() as $phase) {
            $entry = $this->entryAt($period, $jobNumber, $phase);
            $fee = $this->phaseFee($jobNumber, $phase, $entry);
            $before = $this->phaseBilledBefore($jobNumber, $phase, $period);

            $phases[] = [
                'phase' => $phase,
                'fee' => $fee,
                'feeNote' => $this->feeNotes[$jobNumber][$phase] ?? null,
                'billedBefore' => $before,
                'completionBefore' => $fee !== null && ! Money::isZero($fee) ? Money::div($before, $fee) : null,
                'pctThisMonth' => $entry['pct_complete'] ?? null,
                'amountThisMonth' => $entry['amount'] ?? null,
                'basis' => $this->entryBasis($entry),
                'billedThisMonth' => $entry ? $this->entryBilled($entry) : '0.00',
                'note' => $entry['note'] ?? null,
            ];
        }

        $lines = [];

        foreach ($this->hourLines[$jobNumber] ?? [] as $line) {
            if ($line['period'] !== $period) {
                continue;
            }

            [$resolved, $source] = $this->resolveRate($jobNumber, (string) $line['staff']);

            $lines[] = [
                'id' => (int) $line['id'],
                'staff' => $line['staff'],
                'hours' => $line['hours'],
                'rate' => $line['rate'],
                'amount' => $line['amount'],
                'rateSource' => $line['rate_source'],
                'resolvedRate' => $resolved,
                'resolvedRateSource' => $source,
                'total' => $this->lineTotal($line),
                'note' => $line['note'],
            ];
        }

        $job = $this->jobs[$jobNumber] ?? [];

        return [
            'jobNumber' => $jobNumber,
            'jobName' => $job['job_name'] ?? $jobNumber,
            'developer' => $job['developer'] ?? null,
            'division' => $job['division'] ?? null,
            'lead' => $job['project_lead'] ?? null,
            'caPerMonth' => $job['ca_per_month'] ?? null,
            'phases' => $phases,
            'hourLines' => $lines,
            'hourlyOverride' => $this->hourlyOverride($jobNumber, $period),
            'totals' => $this->jobPeriodTotals($jobNumber, $period),
        ];
    }

    /**
     * The Monthly Billing Report: one block per job billed. Total Extras is a
     * subset of the month's total, never an addition to it.
     *
     * @return array{period: string, status: string, jobCount: int, totalThisMonth: string, totalExtras: string, totalPhaseBilling: string, hoursThisMonth: float, jobs: array<int, array<string, mixed>>}
     */
    public function periodReport(string $period): array
    {
        $blocks = array_map(fn (string $jobNumber) => $this->periodGrid($period, $jobNumber), $this->periodJobs($period));

        $total = Money::round(Money::sum(array_map(fn (array $block) => $block['totals']['billed'], $blocks)));
        $hourly = Money::round(Money::sum(array_map(fn (array $block) => $block['totals']['hourlyBilled'], $blocks)));

        $hours = '0';

        foreach (array_keys($this->hours) as $jobNumber) {
            $hours = Money::add($hours, $this->hoursIn((string) $jobNumber, $period));
        }

        return [
            'period' => $period,
            'status' => $this->periodStatus($period),
            'jobCount' => count($blocks),
            'totalThisMonth' => $total,
            'totalExtras' => $hourly,
            'totalPhaseBilling' => Money::round(Money::sub($total, $hourly)),
            'hoursThisMonth' => round(Money::toFloat($hours), 2),
            'jobs' => $blocks,
        ];
    }

    /**
     * Hourly billing ("Extras") month by month, from the billing lines alone.
     *
     * Hours are what the staff lines say was worked; linesTotal is what they bill
     * at their stored rates; billed is what was invoiced (an override wins). The
     * rate divides what the lines with hours bill by those hours, so flat-fee and
     * capped months never read as a rate nobody charged.
     *
     * @return array{months: array<int, array<string, mixed>>, jobMonths: array<int, array<string, mixed>>, personMonths: array<int, array<string, mixed>>, lines: array<int, array<string, mixed>>, jobs: array<string, array<string, mixed>>}
     */
    public function extras(): array
    {
        $phaseByPeriod = [];

        foreach ($this->entries as $entries) {
            foreach ($entries as $entry) {
                $phaseByPeriod[$entry['period']] = Money::add($phaseByPeriod[$entry['period']] ?? '0', $this->entryBilled($entry));
            }
        }

        $blank = ['hours' => 0.0, 'hoursTotal' => '0', 'linesTotal' => '0', 'lineCount' => 0, 'writtenOffHours' => 0.0, 'staff' => []];
        $cells = [];
        $people = [];
        $lines = [];

        foreach ($this->hourLines as $jobNumber => $jobLines) {
            foreach ($jobLines as $line) {
                $key = "{$line['period']}|{$jobNumber}";
                $cell = $cells[$key] ?? $blank;
                $hours = Money::toFloat($line['hours']);
                $total = $this->lineTotal($line);

                $cell['linesTotal'] = Money::add($cell['linesTotal'], $total);
                $cell['lineCount']++;

                if ($hours) {
                    $cell['hours'] += $hours;
                    $cell['hoursTotal'] = Money::add($cell['hoursTotal'], $total);

                    if ($line['amount'] !== null && Money::isZero($line['amount'])) {
                        $cell['writtenOffHours'] += $hours;
                    }
                }

                if ($line['staff']) {
                    $cell['staff'][$line['staff']] = true;

                    $personKey = "{$line['period']}|{$line['staff']}";
                    $person = $people[$personKey] ?? ['period' => $line['period'], 'staff' => $line['staff'], 'hours' => 0.0, 'linesTotal' => '0', 'jobs' => []];
                    $person['hours'] += $hours;
                    $person['linesTotal'] = Money::add($person['linesTotal'], $total);
                    $person['jobs'][$jobNumber] = true;
                    $people[$personKey] = $person;
                }

                $cells[$key] = $cell;

                $lines[] = [
                    'id' => (int) $line['id'],
                    'period' => $line['period'],
                    'jobNumber' => (string) $jobNumber,
                    'staff' => $line['staff'],
                    'hours' => $line['hours'],
                    'rate' => $line['rate'],
                    'amount' => $line['amount'],
                    'total' => $total,
                    'note' => $line['note'],
                ];
            }
        }

        foreach (array_keys($this->hourlyOverrides) as $key) {
            $cells[$key] ??= $blank;
        }

        $jobMonths = [];
        $months = [];

        foreach ($cells as $key => $cell) {
            [$period, $jobNumber] = explode('|', (string) $key, 2);

            $override = $this->hourlyOverride($jobNumber, $period);
            $billed = $this->hourlyBilled($jobNumber, $period);
            $kind = match (true) {
                $override === null => 'lines',
                ! $cell['hours'] => 'flat',
                default => 'adjusted',
            };

            $staff = array_keys($cell['staff']);
            sort($staff);

            $jobMonths[] = [
                'period' => $period,
                'jobNumber' => $jobNumber,
                'hours' => round($cell['hours'], 2),
                'billed' => $billed,
                'linesTotal' => Money::round($cell['linesTotal']),
                'rate' => $cell['hours'] ? round(Money::toFloat($cell['hoursTotal']) / $cell['hours'], 2) : null,
                'override' => $override,
                'kind' => $kind,
                'lineCount' => $cell['lineCount'],
                'writtenOffHours' => round($cell['writtenOffHours'], 2),
                'people' => $staff,
            ];

            $month = $months[$period] ?? ['hours' => 0.0, 'hoursTotal' => '0', 'billed' => '0', 'linesTotal' => '0', 'flat' => '0', 'adjust' => '0', 'writtenOffHours' => 0.0, 'jobs' => 0, 'people' => [], 'lineCount' => 0];
            $month['hours'] += $cell['hours'];
            $month['hoursTotal'] = Money::add($month['hoursTotal'], $cell['hoursTotal']);
            $month['billed'] = Money::add($month['billed'], $billed);
            $month['linesTotal'] = Money::add($month['linesTotal'], $cell['linesTotal']);
            $month['writtenOffHours'] += $cell['writtenOffHours'];
            $month['lineCount'] += $cell['lineCount'];
            $month['people'] += $cell['staff'];

            if ($kind === 'flat') {
                $month['flat'] = Money::add($month['flat'], $billed);
            }

            if ($kind === 'adjusted') {
                $month['adjust'] = Money::add($month['adjust'], Money::sub($billed, $cell['linesTotal']));
            }

            if (! Money::isZero($billed) || $cell['hours']) {
                $month['jobs']++;
            }

            $months[$period] = $month;
        }

        $first = $months ? min(array_keys($months)) : null;
        $monthRows = [];

        foreach ($this->periods() as $period) {
            if ($first === null || $period < $first) {
                continue;
            }

            $month = $months[$period] ?? null;
            $hourly = $month ? Money::round($month['billed']) : '0.00';

            $monthRows[] = [
                'period' => $period,
                'status' => $this->periodStatus($period),
                'hours' => $month ? round($month['hours'], 2) : 0.0,
                'hoursTotal' => $month ? Money::round($month['hoursTotal']) : '0.00',
                'billed' => $hourly,
                'linesTotal' => $month ? Money::round($month['linesTotal']) : '0.00',
                'flat' => $month ? Money::round($month['flat']) : '0.00',
                'adjust' => $month ? Money::round($month['adjust']) : '0.00',
                'rate' => $month && $month['hours'] ? round(Money::toFloat($month['hoursTotal']) / $month['hours'], 2) : null,
                'writtenOffHours' => $month ? round($month['writtenOffHours'], 2) : 0.0,
                'jobs' => $month['jobs'] ?? 0,
                'people' => $month ? count($month['people']) : 0,
                'lineCount' => $month['lineCount'] ?? 0,
                'totalBilled' => Money::round(Money::add($phaseByPeriod[$period] ?? '0', $hourly)),
            ];
        }

        usort($jobMonths, fn (array $left, array $right) => [$left['period'], $left['jobNumber']] <=> [$right['period'], $right['jobNumber']]);

        $personMonths = array_map(function (array $person) {
            $jobs = array_map('strval', array_keys($person['jobs']));
            sort($jobs);

            return [...$person, 'hours' => round($person['hours'], 2), 'linesTotal' => Money::round($person['linesTotal']), 'jobs' => $jobs];
        }, array_values($people));

        usort($personMonths, fn (array $left, array $right) => [$left['period'], $left['staff']] <=> [$right['period'], $right['staff']]);

        $jobs = [];

        foreach (array_unique(array_column($jobMonths, 'jobNumber')) as $jobNumber) {
            $job = $this->jobs[$jobNumber] ?? null;

            if ($job === null) {
                continue;
            }

            $jobs[$jobNumber] = [
                'jobName' => $job['job_name'],
                'developer' => $job['developer'],
                'division' => $job['division'],
                'lead' => $job['project_lead'],
                'active' => (bool) $job['active'],
            ];
        }

        ksort($jobs);

        return [
            'months' => $monthRows,
            'jobMonths' => $jobMonths,
            'personMonths' => $personMonths,
            'lines' => $lines,
            'jobs' => $jobs,
        ];
    }

    public static function monthEnd(string $date): string
    {
        return Carbon::parse($date)->endOfMonth()->toDateString();
    }

    /** @return array<int, string> */
    protected function billedPeriods(string $jobNumber): array
    {
        $periods = collect($this->entries[$jobNumber] ?? [])->pluck('period')
            ->merge(collect($this->hourLines[$jobNumber] ?? [])->pluck('period'));

        foreach (array_keys($this->hourlyOverrides) as $key) {
            [$period, $job] = explode('|', $key, 2);

            if ($job === $jobNumber) {
                $periods->push($period);
            }
        }

        return $periods->unique()->values()->all();
    }

    /** @return array<int, string> */
    protected function hourlyJobsIn(string $period): array
    {
        $jobs = [];

        foreach ($this->hourLines as $jobNumber => $lines) {
            foreach ($lines as $line) {
                if ($line['period'] === $period) {
                    $jobs[$jobNumber] = true;

                    break;
                }
            }
        }

        foreach (array_keys($this->hourlyOverrides) as $key) {
            [$overridePeriod, $jobNumber] = explode('|', $key, 2);

            if ($overridePeriod === $period) {
                $jobs[$jobNumber] = true;
            }
        }

        return array_keys($jobs);
    }

    /**
     * @param  iterable<object>  $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function groupByJob(iterable $rows, string $dateColumn = 'period'): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $row[$dateColumn] = $this->period($row[$dateColumn]);
            $grouped[$row['job_number']][] = $row;
        }

        return $grouped;
    }

    protected function period(mixed $value): string
    {
        return substr((string) $value, 0, 10);
    }
}

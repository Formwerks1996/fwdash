<?php

namespace App\Actions\Billing;

use App\Billing\BillingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * Rewrites project_financials from the billing engine, so the stored figures
 * are always the ones the dashboard shows. Pass a job number to refresh one
 * project after an edit; pass nothing to refresh them all after an import.
 */
class RefreshProjectFinancialsAction
{
    /**
     * Works from its own engine: reloading the request's shared one would hand
     * stale or half-written data to whatever resolved it first.
     */
    public function execute(?string $jobNumber = null): void
    {
        $engine = new BillingEngine;

        $jobNumbers = $jobNumber === null
            ? array_keys($engine->jobs())
            : array_filter([$jobNumber], fn (string $number) => $engine->job($number) !== null);

        $now = now();

        $rows = array_map(fn (string $number) => $this->row($engine->jobSummary($number), $now), $jobNumbers);

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('project_financials')->upsert($chunk, ['job_number']);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function row(array $summary, CarbonImmutable $now): array
    {
        return [
            'job_number' => $summary['jobNumber'],
            'contract' => $summary['contract'],
            'billed' => $summary['billed'],
            'phase_billed' => $summary['phaseBilled'],
            'hourly_billed' => $summary['hourlyBilled'],
            'billed_pct' => $summary['billedPct'] === null ? null : round($summary['billedPct'], 6),
            'billed_by_phase' => json_encode($summary['byPhase']),
            'balance' => $summary['balance'],
            'balance_design' => $summary['balanceDesign'],
            'balance_ca' => $summary['balanceCA'],
            'payments_total' => $summary['paymentsTotal'],
            'outstanding' => $summary['outstanding'],
            'last_payment_date' => $summary['lastPaymentDate'],
            'last_payment_amount' => $summary['lastPaymentAmount'],
            'consultant_total' => $summary['consultantTotal'],
            'hours_total' => $summary['hoursTotal'],
            'opening_hours' => $summary['openingHours'],
            'rate_per_hour' => $summary['ratePerHour'],
            'refreshed_at' => $now,
        ];
    }
}

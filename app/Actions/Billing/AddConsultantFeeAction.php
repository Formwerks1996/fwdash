<?php

namespace App\Actions\Billing;

use App\Models\ConsultantFee;
use App\Models\Project;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * A consultant's charge against a job. Given hours and no total, the total is
 * hours x the consultant's latest rate on or before the period x its exchange
 * rate, kept unrounded as the workbook import stores it. Not gated on period
 * status: a consultant's invoice can arrive after the month is closed.
 */
class AddConsultantFeeAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(string $period, string $jobNumber, ?string $consultant, ?string $hours, ?string $total, ?string $note = null): ConsultantFee
    {
        $consultant = trim((string) $consultant) ?: null;
        $note = trim((string) $note) ?: null;

        if (! Project::whereKey($jobNumber)->exists()) {
            throw ValidationException::withMessages(['consultant' => __('Unknown job :job.', ['job' => $jobNumber])]);
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            throw ValidationException::withMessages(['consultant' => __('A billing period is a month-end date, YYYY-MM-DD.')]);
        }

        if ($consultant === null) {
            throw ValidationException::withMessages(['consultant' => __('Name the consultant.')]);
        }

        foreach (['hours' => $hours, 'total' => $total] as $field => $value) {
            if ($value !== null && ! is_numeric($value)) {
                throw ValidationException::withMessages(['consultant' => __('The :field must be a number.', ['field' => $field])]);
            }
        }

        $basis = null;

        if ($total === null && $hours !== null) {
            $rate = DB::table('consultant_rates')
                ->where('consultant', $consultant)
                ->where('period', '<=', $period)
                ->orderByDesc('period')
                ->first();

            if ($rate && $rate->rate !== null && ! Money::isZero($rate->rate)) {
                $exchange = $rate->exchange_rate !== null && ! Money::isZero($rate->exchange_rate) ? $rate->exchange_rate : 1;
                $total = rtrim(rtrim(Money::mul(Money::mul($hours, $rate->rate), $exchange), '0'), '.');
                $basis = ['rate' => (string) $rate->rate, 'exchange_rate' => (string) $exchange, 'rate_period' => substr((string) $rate->period, 0, 10)];
            }
        }

        if ($total === null) {
            throw ValidationException::withMessages(['consultant' => __('No rate for :consultant on or before :period — enter the total.', ['consultant' => $consultant, 'period' => $period])]);
        }

        return DB::transaction(function () use ($period, $jobNumber, $consultant, $hours, $total, $note, $basis) {
            $fee = ConsultantFee::create([
                'period' => $period,
                'job_number' => $jobNumber,
                'consultant' => $consultant,
                'hours' => $hours,
                'total' => $total,
                'note' => $note,
            ]);

            $this->audit->log('insert', 'consultant_fees', $fee->id, after: array_filter([
                'period' => $period,
                'job_number' => $jobNumber,
                'consultant' => $consultant,
                'hours' => $hours,
                'total' => $total,
                'computed_from' => $basis,
            ], fn (mixed $value) => $value !== null));

            return $fee;
        });
    }
}

<?php

namespace App\Actions\Billing;

use App\Models\MonthHourly;
use App\Models\Project;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * The hourly figure a job-month bills when it is not the sum of its lines: a
 * flat fee, a cap, a top-up or a credit. Clearing it lets the lines bill again.
 */
class SetHourlyOverrideAction
{
    public function __construct(
        protected AuditLogger $audit,
        protected EnsurePeriodIsOpenAction $ensurePeriodIsOpen,
    ) {}

    public function execute(string $period, string $jobNumber, ?string $amount, ?string $note = null): void
    {
        if (! Project::whereKey($jobNumber)->exists()) {
            throw ValidationException::withMessages(['billing' => __('Unknown job :job.', ['job' => $jobNumber])]);
        }

        if ($amount !== null && ! is_numeric($amount)) {
            throw ValidationException::withMessages(['billing' => __('The flat amount must be a number.')]);
        }

        $this->ensurePeriodIsOpen->execute($period);

        $existing = MonthHourly::where('period', $period)->where('job_number', $jobNumber)->first();
        $before = $existing?->only(['amount', 'note']);
        $key = "{$period}|{$jobNumber}";

        DB::transaction(function () use ($period, $jobNumber, $amount, $note, $existing, $before, $key) {
            if ($amount === null) {
                $existing?->delete();

                $this->audit->log('delete', 'month_hourly', $key, before: $before);

                return;
            }

            $after = ['amount' => $amount, 'note' => $note ?? $existing?->note];

            MonthHourly::updateOrCreate(['period' => $period, 'job_number' => $jobNumber], $after);

            $this->audit->log($existing ? 'update' : 'insert', 'month_hourly', $key, before: $before, after: $after);
        });
    }
}

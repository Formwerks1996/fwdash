<?php

namespace App\Actions\Billing;

use App\Models\BillingPeriod;
use App\Models\MonthEntry;
use App\Models\ProjectPhaseFee;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * A phase draw is a percentage OR a typed dollar figure; saving one clears the
 * other, and clearing both removes the row. The current fee is frozen onto the
 * entry so a later amendment cannot restate what was invoiced.
 */
class SaveMonthEntryAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(string $period, string $jobNumber, string $phase, ?string $pct, ?string $amount, ?string $note = null): void
    {
        if ($pct !== null && $amount !== null) {
            throw ValidationException::withMessages(['entry' => __('Give a percentage or a dollar figure, not both.')]);
        }

        $billingPeriod = BillingPeriod::firstOrCreate(['period' => $period], ['status' => 'open']);

        if ($billingPeriod->isClosed()) {
            throw ValidationException::withMessages(['entry' => __('Billing period :period is closed.', ['period' => $period])]);
        }

        $entry = MonthEntry::where(['period' => $period, 'job_number' => $jobNumber, 'phase_code' => $phase])->first();
        $before = $entry?->only(['pct_complete', 'amount', 'note']);
        $key = "{$period}|{$jobNumber}|{$phase}";

        DB::transaction(function () use ($period, $jobNumber, $phase, $pct, $amount, $note, $entry, $before, $key) {
            if ($pct === null && $amount === null && ($note === null || $note === '')) {
                $entry?->delete();

                $this->audit->log('delete', 'month_entries', $key, before: $before);

                return;
            }

            $after = [
                'pct_complete' => $pct,
                'amount' => $amount,
                'note' => $note,
                'fee_at_entry' => ProjectPhaseFee::where('job_number', $jobNumber)->where('phase_code', $phase)->value('fee'),
            ];

            MonthEntry::updateOrCreate(['period' => $period, 'job_number' => $jobNumber, 'phase_code' => $phase], $after);

            $this->audit->log($entry ? 'update' : 'insert', 'month_entries', $key, before: $before, after: $after);
        });
    }
}

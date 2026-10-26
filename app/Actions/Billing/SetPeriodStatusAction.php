<?php

namespace App\Actions\Billing;

use App\Models\BillingPeriod;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Closing a month freezes its phase draws, hour lines and hourly overrides.
 * Fees, payments and consultant fees stay editable: they are not month-end
 * entries.
 */
class SetPeriodStatusAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(string $period, string $status, ?User $user = null): BillingPeriod
    {
        if (! in_array($status, ['open', 'closed'], true)) {
            throw ValidationException::withMessages(['billing' => __('Status must be open or closed.')]);
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            throw ValidationException::withMessages(['billing' => __('A billing period is a month-end date, YYYY-MM-DD.')]);
        }

        $user ??= auth()->user();

        return DB::transaction(function () use ($period, $status, $user) {
            $billingPeriod = BillingPeriod::find($period);
            $before = $billingPeriod?->only(['period', 'status', 'closed_at', 'closed_by']);

            $billingPeriod ??= new BillingPeriod(['period' => $period]);
            $billingPeriod->fill([
                'status' => $status,
                'closed_at' => $status === 'closed' ? now() : null,
                'closed_by' => $status === 'closed' ? $user?->name : null,
            ])->save();

            $this->audit->log($status === 'closed' ? 'close' : 'reopen', 'billing_periods', $period, before: $before, after: ['status' => $status]);

            return $billingPeriod;
        });
    }
}

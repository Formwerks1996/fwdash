<?php

namespace App\Actions\Billing;

use App\Models\BillingPeriod;
use Illuminate\Validation\ValidationException;

/*
 * A closed billing period takes no phase draws, hour lines or hourly
 * overrides. Writing to a period with no row yet opens it, as the old server
 * did.
 */
class EnsurePeriodIsOpenAction
{
    public function execute(string $period, string $errorKey = 'billing'): BillingPeriod
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            throw ValidationException::withMessages([$errorKey => __('A billing period is a month-end date, YYYY-MM-DD.')]);
        }

        $billingPeriod = BillingPeriod::firstOrCreate(['period' => $period], ['status' => 'open']);

        if ($billingPeriod->isClosed()) {
            throw ValidationException::withMessages([$errorKey => __('Billing period :period is closed.', ['period' => $period])]);
        }

        return $billingPeriod;
    }
}

<?php

namespace App\Actions\Billing;

use App\Models\Payment;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePaymentAction
{
    public function __construct(protected AuditLogger $audit) {}

    /** @param array{paid_on?: string, amount?: string, note?: ?string} $attributes */
    public function execute(string $jobNumber, array $attributes, ?Payment $payment = null): Payment
    {
        if (array_key_exists('amount', $attributes) && (float) $attributes['amount'] == 0) {
            throw ValidationException::withMessages(['amount' => __('A payment needs an amount.')]);
        }

        return DB::transaction(function () use ($jobNumber, $attributes, $payment) {
            if (! $payment) {
                $payment = Payment::create([...$attributes, 'job_number' => $jobNumber, 'source' => 'app']);

                $this->audit->log('insert', 'payments', $payment->id, after: [...$attributes, 'job_number' => $jobNumber]);

                return $payment;
            }

            $before = $payment->only(['paid_on', 'amount', 'note']);
            $payment->update($attributes);

            $this->audit->log('update', 'payments', $payment->id, before: $before, after: $attributes);

            return $payment;
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $before = $payment->only(['paid_on', 'job_number', 'amount', 'note']);
            $payment->delete();

            $this->audit->log('delete', 'payments', $payment->id, before: $before);
        });
    }
}

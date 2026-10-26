<?php

namespace App\Actions\Billing;

use App\Models\MonthlyTarget;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * A revenue target per calendar month. Not billing data, so no period gating.
 * Saving an amount keeps the month's note; a blank amount clears the target.
 */
class SetMonthlyTargetAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(string $month, ?string $amount): void
    {
        $this->validateMonth($month);
        $amount = $this->validatedAmount($amount);

        DB::transaction(fn () => $this->write($month, $amount));
    }

    public function saveNote(string $month, ?string $note): void
    {
        $this->validateMonth($month);

        $target = MonthlyTarget::find($month);

        if (! $target) {
            throw ValidationException::withMessages(['target' => __('Set a target for the month before adding a note.')]);
        }

        DB::transaction(fn () => $this->write($month, (string) $target->amount, trim((string) $note) ?: null));
    }

    /** @param array<int, string> $months */
    public function fill(array $months, ?string $amount): void
    {
        foreach ($months as $month) {
            $this->validateMonth($month);
        }

        $amount = $this->validatedAmount($amount);

        if ($amount === null) {
            throw ValidationException::withMessages(['target' => __('Type an amount to fill with first.')]);
        }

        DB::transaction(function () use ($months, $amount) {
            foreach ($months as $month) {
                $this->write($month, $amount);
            }
        });
    }

    /**
     * "300,000", "$300,000", "300k" and "0.3m" are all 300000; blank is null.
     */
    public static function parseAmount(?string $input): ?string
    {
        $value = strtolower(str_replace(['$', ',', ' '], '', trim((string) $input)));

        if ($value === '') {
            return null;
        }

        $multiplier = match (substr($value, -1)) {
            'k' => 1_000,
            'm' => 1_000_000,
            default => 1,
        };

        $number = $multiplier === 1 ? $value : substr($value, 0, -1);

        if (! is_numeric($number)) {
            throw ValidationException::withMessages(['target' => __('":input" isn\'t an amount.', ['input' => trim((string) $input)])]);
        }

        return (string) round((float) $number * $multiplier);
    }

    protected function write(string $month, ?string $amount, string|false $note = false): void
    {
        $target = MonthlyTarget::find($month);
        $before = $target?->only(['month', 'amount', 'note', 'updated_at']);

        if ($amount === null) {
            $target?->delete();

            $this->audit->log('target.clear', 'monthly_targets', $month, before: $before);

            return;
        }

        $note = $note === false ? $target?->note : $note;

        MonthlyTarget::updateOrCreate(['month' => $month], ['amount' => $amount, 'note' => $note]);

        $this->audit->log('target.set', 'monthly_targets', $month, before: $before, after: ['month' => $month, 'amount' => $amount, 'note' => $note]);
    }

    protected function validateMonth(string $month): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages(['target' => __('Month must be YYYY-MM.')]);
        }
    }

    protected function validatedAmount(?string $amount): ?string
    {
        $amount = str_replace([',', '$', ' '], '', trim((string) $amount));

        if ($amount === '') {
            return null;
        }

        if (! is_numeric($amount)) {
            throw ValidationException::withMessages(['target' => __('The amount must be a number.')]);
        }

        if ((float) $amount < 0) {
            throw ValidationException::withMessages(['target' => __('A target can\'t be negative.')]);
        }

        return $amount;
    }
}

<?php

namespace App\Actions\Billing;

use App\Billing\BillingEngine;
use App\Models\HourLine;
use App\Models\Project;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Time and materials. The rate is stored on the line: when none is typed the
 * rate table supplies a default and the line records where it came from. A
 * typed amount replaces hours x rate (0 means worked, not billed).
 */
class SaveHourLineAction
{
    public function __construct(
        protected AuditLogger $audit,
        protected EnsurePeriodIsOpenAction $ensurePeriodIsOpen,
        protected BillingEngine $engine,
    ) {}

    /** @param array{staff?: ?string, hours?: ?string, rate?: ?string, amount?: ?string, note?: ?string} $attributes */
    public function execute(string $period, string $jobNumber, array $attributes, ?HourLine $line = null): HourLine
    {
        if ($line) {
            $period = substr((string) $line->period, 0, 10);
            $jobNumber = $line->job_number;
        }

        $this->ensurePeriodIsOpen->execute($period);

        if (! Project::whereKey($jobNumber)->exists()) {
            throw ValidationException::withMessages(['billing' => __('Unknown job :job.', ['job' => $jobNumber])]);
        }

        $values = $this->validated($jobNumber, $attributes);

        return DB::transaction(function () use ($period, $jobNumber, $values, $line) {
            if (! $line) {
                $line = HourLine::create([...$values, 'period' => $period, 'job_number' => $jobNumber]);

                $this->audit->log('insert', 'hour_lines', $line->id, after: [
                    'period' => $period,
                    'job_number' => $jobNumber,
                    ...collect($values)->only(['staff', 'hours', 'rate', 'amount', 'note'])->all(),
                ]);

                return $line;
            }

            $before = $line->snapshot();
            $line->update($values);

            $this->audit->log('update', 'hour_lines', $line->id, before: $before, after: collect($values)->only(['staff', 'hours', 'rate', 'amount', 'note'])->all());

            return $line;
        });
    }

    public function delete(HourLine $line): void
    {
        $this->ensurePeriodIsOpen->execute(substr((string) $line->period, 0, 10));

        DB::transaction(function () use ($line) {
            $before = $line->snapshot();
            $line->delete();

            $this->audit->log('delete', 'hour_lines', $before['id'], before: $before);
        });
    }

    /**
     * Put a removed line back exactly as it was, every column included.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function restore(array $snapshot): HourLine
    {
        $this->ensurePeriodIsOpen->execute((string) $snapshot['period']);

        return DB::transaction(function () use ($snapshot) {
            $line = HourLine::create($snapshot);

            $this->audit->log('insert', 'hour_lines', $line->id, after: $snapshot);

            return $line;
        });
    }

    /**
     * @param  array{staff?: ?string, hours?: ?string, rate?: ?string, amount?: ?string, note?: ?string}  $attributes
     * @return array{staff: ?string, hours: ?string, rate: ?string, amount: ?string, note: ?string, rate_source: string}
     */
    protected function validated(string $jobNumber, array $attributes): array
    {
        $staff = $this->text($attributes['staff'] ?? null);
        $hours = $this->number($attributes['hours'] ?? null, __('Hours'));
        $rate = $this->number($attributes['rate'] ?? null, __('Rate'));
        $amount = $this->number($attributes['amount'] ?? null, __('Amount'));

        if ($hours === null && $amount === null) {
            throw ValidationException::withMessages(['billing' => __('Pick a staff member and enter hours, or type an amount.')]);
        }

        if ($hours !== null && (float) $hours < 0) {
            throw ValidationException::withMessages(['billing' => __('Hours cannot be negative.')]);
        }

        if ($rate !== null && (float) $rate < 0) {
            throw ValidationException::withMessages(['billing' => __('A rate cannot be negative.')]);
        }

        $source = 'manual';

        if ($rate === null && $amount === null) {
            [$rate, $source] = $this->engine->resolveRate($jobNumber, (string) $staff);

            if ($rate === null) {
                throw ValidationException::withMessages(['billing' => __('No rate for :staff on :job (:source) — give a rate explicitly, or fix the staff or job rate table.', [
                    'staff' => $staff ?? __('a blank name'),
                    'job' => $jobNumber,
                    'source' => $source,
                ])]);
            }

            $rate = (string) $rate;
        }

        return [
            'staff' => $staff,
            'hours' => $hours,
            'rate' => $rate,
            'amount' => $amount,
            'note' => $this->text($attributes['note'] ?? null),
            'rate_source' => $source,
        ];
    }

    protected function number(?string $value, string $label): ?string
    {
        $value = trim(str_replace(['$', ',', ' '], '', (string) $value));

        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['billing' => __(':label must be a number.', ['label' => $label])]);
        }

        return $value;
    }

    protected function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

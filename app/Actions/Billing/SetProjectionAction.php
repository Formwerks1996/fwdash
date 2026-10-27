<?php

namespace App\Actions\Billing;

use App\Models\MonthlyProjection;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Sets one month's projected billing or cost. Clearing both removes the month.
 */
class SetProjectionAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(string $month, string $field, ?string $typed): void
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw ValidationException::withMessages(['projection' => __('Unknown month :month.', ['month' => $month])]);
        }

        if (! in_array($field, MonthlyProjection::Fields, true)) {
            throw ValidationException::withMessages(['projection' => __('A projection is billing or cost.')]);
        }

        $value = str_replace([',', '$', ' '], '', trim((string) $typed));

        if ($value !== '' && (! is_numeric($value) || (float) $value < 0)) {
            throw ValidationException::withMessages(['projection' => __('A projection must be a number, zero or more.')]);
        }

        $value = $value === '' ? null : number_format((float) $value, 2, '.', '');
        $projection = MonthlyProjection::firstOrNew(['month' => $month]);
        $before = $projection->exists ? $projection->only(MonthlyProjection::Fields) : null;

        DB::transaction(function () use ($projection, $field, $value, $before, $month) {
            $projection->fill([$field => $value, 'set_by' => auth()->user()->name ?? 'system']);

            if ($projection->billed === null && $projection->cost === null) {
                if ($projection->exists) {
                    $projection->delete();
                }

                $this->audit->log('delete', 'monthly_projections', $month, before: $before);

                return;
            }

            $projection->save();
            $this->audit->log($before ? 'update' : 'insert', 'monthly_projections', $month, before: $before, after: $projection->only(MonthlyProjection::Fields));
        });
    }
}

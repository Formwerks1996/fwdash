<?php

namespace App\Actions\Billing;

use App\Models\Extra;
use App\Models\HourLine;
use App\Models\Project;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Adds, changes and removes extras. Removing one leaves its hourly lines
 * untagged rather than deleting them: the billing stands either way.
 */
class SaveExtraAction
{
    public function __construct(protected AuditLogger $audit) {}

    /** @param  array<string, mixed>  $attributes */
    public function save(array $attributes, ?Extra $extra = null): Extra
    {
        $values = $this->validated($attributes, $extra);

        return DB::transaction(function () use ($values, $extra) {
            if ($extra === null) {
                $extra = Extra::create($values);
                $this->audit->log('insert', 'extras', $extra->id, after: $extra->snapshot());

                return $extra;
            }

            $before = $extra->snapshot();
            $extra->update($values);
            $this->audit->log('update', 'extras', $extra->id, before: $before, after: $extra->snapshot());

            return $extra;
        });
    }

    public function setStatus(Extra $extra, string $status): void
    {
        $this->save([...$extra->snapshot(), 'status' => $status], $extra);
    }

    public function delete(Extra $extra): void
    {
        DB::transaction(function () use ($extra) {
            HourLine::where('extra_id', $extra->id)->update(['extra_id' => null]);

            $before = $extra->snapshot();
            $extra->delete();

            $this->audit->log('delete', 'extras', $extra->id, before: $before);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{job_number: string, code: ?string, title: string, status: string, budget_amount: ?string, budget_hours: ?string, note: ?string}
     */
    protected function validated(array $attributes, ?Extra $extra): array
    {
        $jobNumber = trim((string) ($attributes['job_number'] ?? $extra?->job_number ?? ''));
        $title = trim((string) ($attributes['title'] ?? ''));
        $status = (string) ($attributes['status'] ?? 'proposed');

        if (! Project::whereKey($jobNumber)->exists()) {
            throw ValidationException::withMessages(['extra' => __('Pick a project for this extra.')]);
        }

        if ($extra !== null && $jobNumber !== $extra->job_number && $extra->hourLines()->exists()) {
            throw ValidationException::withMessages(['extra' => __('This extra has hourly lines on :job; retag them before moving it.', ['job' => $extra->job_number])]);
        }

        if ($title === '' || mb_strlen($title) > 200) {
            throw ValidationException::withMessages(['extra' => __('An extra needs a title (200 characters at most).')]);
        }

        if (! in_array($status, Extra::Statuses, true)) {
            throw ValidationException::withMessages(['extra' => __('An extra is proposed, active, complete or rejected.')]);
        }

        return [
            'job_number' => $jobNumber,
            'code' => trim((string) ($attributes['code'] ?? '')) ?: null,
            'title' => $title,
            'status' => $status,
            'budget_amount' => $this->amount($attributes['budget_amount'] ?? null, __('budget')),
            'budget_hours' => $this->amount($attributes['budget_hours'] ?? null, __('hours')),
            'note' => trim((string) ($attributes['note'] ?? '')) ?: null,
        ];
    }

    /** A typed figure such as "$10,500" or "40", or null when left blank. */
    protected function amount(mixed $value, string $label): ?string
    {
        $typed = str_replace([',', '$', ' '], '', trim((string) $value));

        if ($typed === '') {
            return null;
        }

        if (! is_numeric($typed) || (float) $typed < 0) {
            throw ValidationException::withMessages(['extra' => __('The :label must be a number, zero or more.', ['label' => $label])]);
        }

        return number_format((float) $typed, 2, '.', '');
    }
}

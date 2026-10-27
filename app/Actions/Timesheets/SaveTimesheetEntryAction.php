<?php

namespace App\Actions\Timesheets;

use App\Enums\Phase;
use App\Enums\TimeCategory;
use App\Models\Project;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Writes one cell of a person's timesheet. Blank or zero hours clear the cell.
 * Hours are quarter hours, as in Mosaic, and "1:30" means an hour and a half.
 */
class SaveTimesheetEntryAction
{
    public const MaxHoursPerDay = 24;

    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function execute(User $user, string $rowKey, string $date, ?string $hours, ?string $note = null): ?TimesheetEntry
    {
        $row = $this->validatedRow($rowKey);
        $day = $this->validatedDate($date);
        $hours = self::parseHours($hours);
        $note = trim((string) $note) ?: null;

        $entry = TimesheetEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $day)
            ->where('category', $row['category'])
            ->where(fn ($query) => $row['job_number'] === null ? $query->whereNull('job_number') : $query->where('job_number', $row['job_number']))
            ->where(fn ($query) => $row['phase_code'] === null ? $query->whereNull('phase_code') : $query->where('phase_code', $row['phase_code']))
            ->first();

        if ($hours === null) {
            if ($entry) {
                $this->delete($entry);
            }

            return null;
        }

        $otherHours = (float) TimesheetEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $day)
            ->when($entry, fn ($query) => $query->whereKeyNot($entry->id))
            ->sum('hours');

        if ($otherHours + (float) $hours > self::MaxHoursPerDay) {
            throw ValidationException::withMessages(['timesheet' => __('That would make :day more than :max hours.', [
                'day' => $day->format('l j M'),
                'max' => self::MaxHoursPerDay,
            ])]);
        }

        return DB::transaction(function () use ($user, $row, $day, $hours, $note, $entry) {
            if (! $entry) {
                $entry = TimesheetEntry::create([
                    ...$row,
                    'user_id' => $user->id,
                    'work_date' => $day->toDateString(),
                    'hours' => $hours,
                    'note' => $note,
                ]);

                $this->audit->log('insert', 'timesheet_entries', $entry->id, after: $entry->snapshot());

                return $entry;
            }

            $before = $entry->snapshot();
            $entry->update(['hours' => $hours, 'note' => $note]);

            $this->audit->log('update', 'timesheet_entries', $entry->id, before: $before, after: $entry->snapshot());

            return $entry;
        });
    }

    public function delete(TimesheetEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $before = $entry->snapshot();
            $entry->delete();

            $this->audit->log('delete', 'timesheet_entries', $before['id'], before: $before);
        });
    }

    /** Hours as a decimal string, or null for a blank or zero cell. */
    public static function parseHours(?string $value): ?string
    {
        $value = trim(str_replace([' ', 'h', 'H'], '', (string) $value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+):([0-5]\d)$/', $value, $match)) {
            $value = (string) ((int) $match[1] + (int) $match[2] / 60);
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['timesheet' => __('":value" isn\'t a number of hours.', ['value' => $value])]);
        }

        $hours = (float) $value;

        if ($hours < 0) {
            throw ValidationException::withMessages(['timesheet' => __('Hours cannot be negative.')]);
        }

        if ($hours == 0) {
            return null;
        }

        if (fmod($hours * 4, 1) != 0) {
            throw ValidationException::withMessages(['timesheet' => __('Log time in quarter hours (0.25, 0.5, 0.75).')]);
        }

        return number_format($hours, 2, '.', '');
    }

    /** @return array{category: TimeCategory, job_number: ?string, phase_code: ?string} */
    protected function validatedRow(string $rowKey): array
    {
        try {
            $row = TimesheetEntry::parseRowKey($rowKey);
        } catch (\ValueError) {
            throw ValidationException::withMessages(['timesheet' => __('Unknown kind of time.')]);
        }

        if (! $row['category']->needsProject()) {
            return [...$row, 'job_number' => null, 'phase_code' => null];
        }

        if ($row['job_number'] === null || ! Project::whereKey($row['job_number'])->exists()) {
            throw ValidationException::withMessages(['timesheet' => __('Pick a project for project time.')]);
        }

        if ($row['phase_code'] !== null && ! in_array($row['phase_code'], [...Phase::codes(), Phase::Hours], true)) {
            throw ValidationException::withMessages(['timesheet' => __('Unknown phase :phase.', ['phase' => $row['phase_code']])]);
        }

        return $row;
    }

    protected function validatedDate(string $date): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['timesheet' => __('Unknown date.')]);
        }
    }
}

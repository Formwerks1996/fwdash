<?php

namespace App\Models;

use App\Enums\TimeCategory;
use Carbon\CarbonImmutable;
use Database\Factories\TimesheetEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cell of a person's weekly timesheet.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $work_date
 * @property TimeCategory $category
 * @property ?string $job_number
 * @property ?string $phase_code
 * @property string $hours
 * @property ?string $note
 */
#[Fillable(['user_id', 'work_date', 'category', 'job_number', 'phase_code', 'hours', 'note'])]
class TimesheetEntry extends Model
{
    /** @use HasFactory<TimesheetEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'category' => TimeCategory::class,
            'hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'job_number');
    }

    /** @param  Builder<TimesheetEntry>  $query */
    public function scopeForWeek(Builder $query, CarbonImmutable $weekStart): void
    {
        $query->whereBetween('work_date', [$weekStart->toDateString(), $weekStart->addDays(6)->toDateString()]);
    }

    /** The grid row this entry belongs to: what the time was for, not when. */
    public function rowKey(): string
    {
        return self::makeRowKey($this->category, $this->job_number, $this->phase_code);
    }

    public static function makeRowKey(TimeCategory $category, ?string $jobNumber, ?string $phaseCode): string
    {
        return implode('|', [$category->value, $jobNumber ?? '', $phaseCode ?? '']);
    }

    /** @return array{category: TimeCategory, job_number: ?string, phase_code: ?string} */
    public static function parseRowKey(string $rowKey): array
    {
        [$category, $jobNumber, $phaseCode] = array_pad(explode('|', $rowKey, 3), 3, '');

        return [
            'category' => TimeCategory::from($category),
            'job_number' => $jobNumber === '' ? null : $jobNumber,
            'phase_code' => $phaseCode === '' ? null : $phaseCode,
        ];
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            ...$this->only(['id', 'user_id', 'job_number', 'phase_code', 'hours', 'note']),
            'work_date' => $this->work_date->toDateString(),
            'category' => $this->category->value,
        ];
    }
}

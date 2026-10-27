<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone booked onto a job (and usually one of its phases) for a stretch of
 * weekdays at so many hours a day, as Mosaic's work plans were.
 *
 * @property int $id
 * @property string $job_number
 * @property ?int $project_phase_id
 * @property string $member_name
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property string $daily_hours
 */
#[Fillable(['job_number', 'project_phase_id', 'member_name', 'start_date', 'end_date', 'daily_hours'])]
class ProjectWorkPlan extends Model
{
    /** Formwerks plans a full day at 7.5 hours, not 8. */
    public const FullDay = 7.5;

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'daily_hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ProjectPhase, $this> */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'project_phase_id');
    }

    public function totalHours(): float
    {
        return self::hoursBetween($this->start_date->format('Y-m-d'), $this->end_date->format('Y-m-d'), (float) $this->daily_hours);
    }

    /** Weekdays from start to end, inclusive, at so many hours a day. */
    public static function hoursBetween(string $start, string $end, float $dailyHours): float
    {
        if ($end < $start) {
            return 0.0;
        }

        $weekdays = 0;

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $weekdays += $day->isWeekend() ? 0 : 1;
        }

        return $weekdays * $dailyHours;
    }
}

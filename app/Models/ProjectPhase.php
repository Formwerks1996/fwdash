<?php

namespace App\Models;

use App\Enums\Phase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One phase of a job as its contract names it, e.g. "Landscape Master Plan".
 * Month End bills it under billing_code: a Phase code, or Phase::Hours for a
 * time-and-materials phase, which has no fixed fee.
 *
 * @property int $id
 * @property string $job_number
 * @property int $seq
 * @property string $name
 * @property string $billing_code
 * @property ?string $fee
 * @property ?string $estimated_hours
 * @property ?CarbonImmutable $start_date
 * @property ?CarbonImmutable $end_date
 */
#[Fillable(['job_number', 'seq', 'name', 'billing_code', 'fee', 'estimated_hours', 'start_date', 'end_date'])]
class ProjectPhase extends Model
{
    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    /** @return HasMany<ProjectHourBudget, $this> */
    public function hourBudgets(): HasMany
    {
        return $this->hasMany(ProjectHourBudget::class);
    }

    public function isHourly(): bool
    {
        return $this->billing_code === Phase::Hours;
    }
}

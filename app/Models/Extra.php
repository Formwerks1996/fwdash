<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work on a job beyond its contract phases, at a set amount, a set number of
 * hours, or open-ended hourly. Hourly billing lines are tagged to it.
 *
 * @property int $id
 * @property string $job_number
 * @property ?string $code
 * @property string $title
 * @property string $status
 * @property ?string $budget_amount
 * @property ?string $budget_hours
 * @property ?string $note
 * @property ?int $mosaic_id
 * @property ?string $mosaic_phase
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['job_number', 'code', 'title', 'status', 'budget_amount', 'budget_hours', 'note', 'mosaic_id', 'mosaic_phase'])]
class Extra extends Model
{
    public const Statuses = ['proposed', 'active', 'complete', 'rejected'];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'budget_hours' => 'decimal:2',
        ];
    }

    /** @return HasMany<HourLine, $this> */
    public function hourLines(): HasMany
    {
        return $this->hasMany(HourLine::class);
    }

    /** "8040-04: New parapet roof profile", or just the title. */
    public function label(): string
    {
        return $this->code ? "{$this->code}: {$this->title}" : $this->title;
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return $this->only(['job_number', 'code', 'title', 'status', 'budget_amount', 'budget_hours', 'note']);
    }
}

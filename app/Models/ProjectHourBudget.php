<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The hours one person is budgeted for on one phase of a job.
 *
 * @property int $id
 * @property int $project_phase_id
 * @property string $member_name
 * @property string $hours
 */
#[Fillable(['project_phase_id', 'member_name', 'hours'])]
class ProjectHourBudget extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ProjectPhase, $this> */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'project_phase_id');
    }
}

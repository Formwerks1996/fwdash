<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Hours logged on one Mosaic phase of a job, priced at each person's
 * charge-out rate. phase_code is the billing phase it matches, if any.
 *
 * @property int $id
 * @property string $job_number
 * @property string $mosaic_phase
 * @property ?string $phase_code
 * @property string $hours
 * @property string $charge_out
 * @property string $unrated_hours
 */
#[Fillable(['job_number', 'mosaic_phase', 'phase_code', 'hours', 'charge_out', 'unrated_hours'])]
class ProjectPhaseHours extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'charge_out' => 'decimal:2',
            'unrated_hours' => 'decimal:2',
        ];
    }
}

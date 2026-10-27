<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone Mosaic has on a project, copied in by `mosaic:import-roles`. Read
 * only here: Mosaic is where people are put on projects.
 *
 * @property int $id
 * @property string $email
 * @property string $job_number
 * @property ?string $role
 * @property ?CarbonImmutable $start_date
 * @property ?CarbonImmutable $end_date
 */
#[Fillable(['email', 'job_number', 'role', 'start_date', 'end_date'])]
class MosaicProjectRole extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Roles that have not ended. Mosaic rarely ends one, so this is most of them.
     *
     * @param  Builder<MosaicProjectRole>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('end_date')->orWhere('end_date', '>=', CarbonImmutable::today()->toDateString()));
    }
}

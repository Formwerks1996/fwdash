<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What one person costs the firm an hour from a date on, until their next rate.
 *
 * @property int $id
 * @property string $email
 * @property ?string $name
 * @property string $hourly_cost
 * @property CarbonImmutable $starts_on
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
#[Fillable(['email', 'name', 'hourly_cost', 'starts_on'])]
class StaffCostRate extends Model
{
    protected function casts(): array
    {
        return [
            'hourly_cost' => 'decimal:2',
            'starts_on' => 'immutable_date',
        ];
    }
}

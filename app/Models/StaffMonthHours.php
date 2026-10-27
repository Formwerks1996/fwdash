<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Hours one person logged in a calendar month ("YYYY-MM"), from one source.
 *
 * @property int $id
 * @property string $email
 * @property ?string $name
 * @property string $month
 * @property string $hours
 * @property string $source
 */
#[Fillable(['email', 'name', 'month', 'hours', 'source'])]
class StaffMonthHours extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
        ];
    }
}

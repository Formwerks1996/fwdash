<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The hourly figure a job-month bills when it is not the sum of its lines.
 *
 * @property int $id
 * @property string $period
 * @property string $job_number
 * @property string $amount
 * @property ?string $note
 */
#[Fillable(['period', 'job_number', 'amount', 'note'])]
class MonthHourly extends Model
{
    protected $table = 'month_hourly';

    public $timestamps = false;
}

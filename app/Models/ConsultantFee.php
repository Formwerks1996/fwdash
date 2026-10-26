<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $period
 * @property string $job_number
 * @property ?string $consultant
 * @property ?string $hours
 * @property ?string $total
 * @property ?string $note
 */
#[Fillable(['period', 'job_number', 'consultant', 'hours', 'total', 'note'])]
class ConsultantFee extends Model
{
    public $timestamps = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_number', 'phase_code', 'fee', 'note'])]
class ProjectPhaseFee extends Model
{
    public $timestamps = false;
}

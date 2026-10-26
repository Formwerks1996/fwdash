<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['period', 'job_number', 'phase_code', 'pct_complete', 'amount', 'fee_at_entry', 'note'])]
class MonthEntry extends Model
{
    public $timestamps = false;
}

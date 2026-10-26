<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['period', 'status', 'closed_at', 'closed_by'])]
class BillingPeriod extends Model
{
    protected $primaryKey = 'period';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}

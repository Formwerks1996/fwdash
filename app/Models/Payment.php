<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property CarbonImmutable $paid_on
 * @property string $job_number
 * @property string $amount
 * @property ?string $note
 * @property ?string $source
 */
#[Fillable(['paid_on', 'job_number', 'amount', 'note', 'source'])]
class Payment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date:Y-m-d',
        ];
    }
}

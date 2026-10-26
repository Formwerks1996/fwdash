<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A revenue target for one calendar month, keyed "YYYY-MM".
 *
 * @property string $month
 * @property string $amount
 * @property ?string $note
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
#[Fillable(['month', 'amount', 'note'])]
class MonthlyTarget extends Model
{
    protected $primaryKey = 'month';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}

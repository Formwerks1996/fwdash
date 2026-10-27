<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What the firm expects to bill and spend in one calendar month ("YYYY-MM").
 *
 * @property int $id
 * @property string $month
 * @property ?string $billed
 * @property ?string $cost
 * @property ?string $set_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['month', 'billed', 'cost', 'set_by'])]
class MonthlyProjection extends Model
{
    public const Fields = ['billed', 'cost'];

    protected function casts(): array
    {
        return [
            'billed' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }
}

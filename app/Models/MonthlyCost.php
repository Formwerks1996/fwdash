<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One expense line of one calendar month ("YYYY-MM"): a column of the Costs
 * sheet. The "Payroll" line is payroll; every other line is overhead.
 *
 * @property int $id
 * @property string $month
 * @property string $category
 * @property string $amount
 * @property int $seq
 */
#[Fillable(['month', 'category', 'amount', 'seq'])]
class MonthlyCost extends Model
{
    public const Payroll = 'Payroll';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'seq' => 'integer',
        ];
    }

    /** @return array<string, array{payroll: float, overhead: float, total: float}> keyed by "YYYY-MM" */
    public static function byMonth(): array
    {
        return static::orderBy('month')
            ->get()
            ->groupBy('month')
            ->map(function ($lines) {
                $payroll = (float) $lines->where('category', self::Payroll)->sum('amount');
                $total = (float) $lines->sum('amount');

                return ['payroll' => $payroll, 'overhead' => $total - $payroll, 'total' => $total];
            })
            ->all();
    }

    /**
     * Each category summed over the months given, in the sheet's column order.
     *
     * @param  array<int, string>  $months
     * @return array<string, float>
     */
    public static function byCategory(array $months): array
    {
        return static::whereIn('month', $months)
            ->orderBy('seq')
            ->get()
            ->groupBy('category')
            ->map(fn ($lines) => (float) $lines->sum('amount'))
            ->all();
    }
}

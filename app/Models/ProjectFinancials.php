<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project's billing figures as the engine last worked them out.
 *
 * @property string $job_number
 * @property string $contract
 * @property string $billed
 * @property string $phase_billed
 * @property string $hourly_billed
 * @property ?string $billed_pct
 * @property array<string, string> $billed_by_phase
 * @property string $balance
 * @property string $balance_design
 * @property string $balance_ca
 * @property string $payments_total
 * @property string $outstanding
 * @property ?CarbonImmutable $last_payment_date
 * @property ?string $last_payment_amount
 * @property string $consultant_total
 * @property string $hours_total
 * @property ?string $opening_hours
 * @property ?string $rate_per_hour
 * @property CarbonImmutable $refreshed_at
 */
#[Fillable(['job_number', 'contract', 'billed', 'phase_billed', 'hourly_billed', 'billed_pct', 'billed_by_phase', 'balance', 'balance_design', 'balance_ca', 'payments_total', 'outstanding', 'last_payment_date', 'last_payment_amount', 'consultant_total', 'hours_total', 'opening_hours', 'rate_per_hour', 'refreshed_at'])]
class ProjectFinancials extends Model
{
    protected $primaryKey = 'job_number';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        $money = ['contract', 'billed', 'phase_billed', 'hourly_billed', 'balance', 'balance_design', 'balance_ca', 'payments_total', 'outstanding', 'last_payment_amount', 'consultant_total', 'hours_total', 'opening_hours', 'rate_per_hour'];

        return [
            ...array_fill_keys($money, 'decimal:2'),
            'billed_pct' => 'decimal:6',
            'billed_by_phase' => 'array',
            'last_payment_date' => 'date:Y-m-d',
            'refreshed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'job_number');
    }
}

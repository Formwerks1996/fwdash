<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $job_number
 * @property string $job_name
 * @property ?string $developer
 * @property ?string $division
 * @property ?string $project_lead
 * @property bool $active
 * @property bool $andrew
 * @property ?string $ca_per_month
 * @property ?CarbonImmutable $ifc_date
 * @property ?CarbonImmutable $complete_date
 * @property ?string $notes
 */
#[Fillable(['job_number', 'job_name', 'developer', 'division', 'project_lead', 'active', 'andrew', 'ca_per_month', 'ifc_date', 'complete_date', 'notes'])]
class Project extends Model
{
    protected $primaryKey = 'job_number';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'andrew' => 'boolean',
            'ca_per_month' => 'decimal:2',
            'ifc_date' => 'date:Y-m-d',
            'complete_date' => 'date:Y-m-d',
        ];
    }

    /** @return HasOne<ProjectStatus, $this> */
    public function status(): HasOne
    {
        return $this->hasOne(ProjectStatus::class, 'job_number');
    }

    /** @return HasOne<ProjectFinancials, $this> */
    public function financials(): HasOne
    {
        return $this->hasOne(ProjectFinancials::class, 'job_number');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'job_number');
    }

    /** @return HasMany<ProjectPhaseFee, $this> */
    public function phaseFees(): HasMany
    {
        return $this->hasMany(ProjectPhaseFee::class, 'job_number');
    }

    /** @return HasMany<ProjectPhase, $this> */
    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class, 'job_number')->orderBy('seq');
    }

    /** @return HasMany<ProjectWorkPlan, $this> */
    public function workPlans(): HasMany
    {
        return $this->hasMany(ProjectWorkPlan::class, 'job_number')->orderBy('start_date');
    }

    public static function normalizeJobNumber(string $jobNumber): string
    {
        $jobNumber = trim($jobNumber);

        return str_starts_with($jobNumber, '#') ? $jobNumber : "#{$jobNumber}";
    }
}

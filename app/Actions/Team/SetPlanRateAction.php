<?php

namespace App\Actions\Team;

use App\Billing\ProjectBurn;
use App\Models\AppSetting;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * The rate that turns a fee into the hours it pays for. A value, not a cost:
 * nobody's pay is in the dashboard.
 */
class SetPlanRateAction
{
    public function __construct(protected AuditLogger $audit) {}

    public function execute(mixed $rate): void
    {
        if (! is_numeric($rate) || (float) $rate < 20 || (float) $rate > 500) {
            throw ValidationException::withMessages(['planRate' => __('plan rate must be between 20 and 500')]);
        }

        $value = number_format((float) $rate, 2, '.', '');
        $existing = AppSetting::find(ProjectBurn::PlanRateKey);

        DB::transaction(function () use ($value, $existing) {
            $before = $existing ? ['key' => $existing->key, 'value' => $existing->value] : null;

            AppSetting::updateOrCreate(['key' => ProjectBurn::PlanRateKey], ['value' => $value]);

            $this->audit->log('setting.set', 'app_settings', ProjectBurn::PlanRateKey, before: $before, after: ['key' => ProjectBurn::PlanRateKey, 'value' => $value]);
        });
    }
}

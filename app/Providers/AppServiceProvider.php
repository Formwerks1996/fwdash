<?php

namespace App\Providers;

use App\Actions\Billing\RefreshProjectFinancialsAction;
use App\Billing\BillingEngine;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ConsultantFee;
use App\Models\HourLine;
use App\Models\MonthEntry;
use App\Models\MonthHourly;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectPhaseFee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\Provider as AzureProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(BillingEngine::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        $this->keepProjectFinancialsCurrent();

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('azure', AzureProvider::class);
        });

        Gate::before(function (User $user, string $ability) {
            if (! $user->hasRole(Role::Admin->value)) {
                return null;
            }

            $previewRole = $user->previewRole();

            return $previewRole === null
                || in_array($ability, array_map(fn (Permission $permission) => $permission->value, $previewRole->permissions()), true);
        });
    }

    /**
     * Every in-app billing edit goes through one of these models, so a save or
     * delete re-works that project's row in project_financials once the
     * transaction commits. The workbook import refreshes all rows itself.
     */
    protected function keepProjectFinancialsCurrent(): void
    {
        $models = [Project::class, ProjectPhaseFee::class, MonthEntry::class, HourLine::class, MonthHourly::class, Payment::class, ConsultantFee::class];

        $refresh = fn (Model $model) => DB::afterCommit(
            fn () => app(RefreshProjectFinancialsAction::class)->execute($model->getAttribute('job_number')),
        );

        foreach ($models as $model) {
            $model::saved($refresh);
            $model::deleted($refresh);
        }
    }
}

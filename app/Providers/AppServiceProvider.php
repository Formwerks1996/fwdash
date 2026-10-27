<?php

namespace App\Providers;

use App\Billing\BillingEngine;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
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
}

<?php

namespace App\Providers;

use App\Billing\BillingEngine;
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

        Gate::before(fn (User $user) => $user->hasRole(Role::Admin->value) ? true : null);
    }
}

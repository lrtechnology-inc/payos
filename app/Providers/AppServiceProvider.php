<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Route;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Policies\RoutePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Route::class, RoutePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}

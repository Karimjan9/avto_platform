<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use App\Policies\BusinessPolicy;
use App\Policies\ListingPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(Listing::class, ListingPolicy::class);
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::define('marketplace-admin', fn (User $user) => $user->is_admin);
        RateLimiter::for('marketplace-api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('marketplace-auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('marketplace-web', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
    }
}

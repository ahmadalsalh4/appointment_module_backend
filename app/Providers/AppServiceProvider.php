<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;

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
        // Strict-mode Eloquent by default. Prevents accidental lazy
        // loading of large relations in production.
        Model::shouldBeStrict(! app()->isProduction());

        // --- Authorization gates ----------------------------------------
        // An admin may read/update/delete a staff member only if that
        // staff member is currently managed by them.
        Gate::define('manage-staff', function (Admin $admin, Staff $staff): bool {
            return (int) $staff->admin_id === (int) $admin->id;
        });

        // An admin may manage categories, services, and appointments.
        // Per-admin scoping for those is enforced via dedicated checks
        // in each controller (Category/ServiceController are global by
        // design; AppointmentController is scoped via managed staff).

        // --- Rate limiters ----------------------------------------------
        // The 60/min availability limit is registered alongside the
        // other throttles declared inline on routes.
        RateLimiter::for('availability', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(10)->by($request->input('email').'|'.$request->ip());
        });
    }
}

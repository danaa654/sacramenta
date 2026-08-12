<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Mass Schedule management — create a special/recurring Mass,
        // assign or change a priest, cancel a Mass occurrence, or restore
        // a cancelled one. Per the RBAC spec, Staff's Mass Schedule access
        // is view-only; these actions are Super Admin + Administrator
        // only. There's no MassSchedule Eloquent model to hang a policy
        // off (Mass Schedule entries live on the Reservation model as
        // type = 'mass'), so this is a plain Gate ability rather than a
        // model policy — see MassScheduleController.
        Gate::define('manage-mass-schedule', fn (User $user) => $user->isAdmin());
    }
}